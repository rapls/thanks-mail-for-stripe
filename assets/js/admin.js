/**
 * Thanks Mail for Stripe - Admin Settings JavaScript
 *
 * @package Thanks_Mail_For_Stripe
 */

/* global jQuery, tmfsAdmin */
jQuery(document).ready(function($) {
    // Copy button
    $('.stm-copy-btn').on('click', function() {
        var text = $(this).data('copy');
        navigator.clipboard.writeText(text).then(function() {
            alert(tmfsAdmin.i18n.copied);
        });
    });

    // Toggle password visibility
    $('.stm-toggle-password').on('click', function() {
        var target = $('#' + $(this).data('target'));
        var type = target.attr('type') === 'password' ? 'text' : 'password';
        target.attr('type', type);
        $(this).text(type === 'password' ? tmfsAdmin.i18n.show : tmfsAdmin.i18n.hide);
    });

    // Reset settings
    $('#stm_reset_settings').on('click', function() {
        if (!confirm(tmfsAdmin.i18n.confirmReset)) {
            return;
        }

        var $btn = $(this);
        var $status = $('#stm_reset_status');

        $btn.prop('disabled', true);

        $.ajax({
            url: tmfsAdmin.resetUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tmfsAdmin.nonce);
            },
            success: function() {
                alert(tmfsAdmin.i18n.resetDone);
                location.reload();
            },
            error: function() {
                $status.html('<span style="color: red;">' + tmfsAdmin.i18n.error + '</span>');
                $btn.prop('disabled', false);
            }
        });
    });

    // Send test email
    $('#stm_send_test').on('click', function() {
        var $btn = $(this);
        var $status = $('#stm_test_status');
        var email = $('#stm_test_email').val();
        var lang = $('#stm_test_lang').val();

        if (!email) {
            alert(tmfsAdmin.i18n.enterEmail);
            return;
        }

        $btn.prop('disabled', true);
        $status.text(tmfsAdmin.i18n.sending);

        $.ajax({
            url: tmfsAdmin.testUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tmfsAdmin.nonce);
            },
            data: {
                email: email,
                lang: lang
            },
            success: function(response) {
                if (response.sent) {
                    $status.html('<span style="color: green;">' + tmfsAdmin.i18n.testSent + '</span>');
                } else {
                    $status.html('<span style="color: red;">' + tmfsAdmin.i18n.sendFailed + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">' + tmfsAdmin.i18n.error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // --- Template management ---

    var PER_PAGE = 10;
    var currentPage = 1;
    var searchTerm = '';
    var categoryFilter = '';
    var UNCAT = '__tmfs_uncategorized__';

    // Monotonic counter for browser-added category rows (group index + temp ID),
    // started past the server-rendered rows so it never collides with them.
    var catRowSeq = $('#tmfs-categories-list .tmfs-category-row').length;

    /**
     * Get all template cards.
     */
    function getCards() {
        return $('#tmfs-templates-container .stm-template-card');
    }

    /**
     * Read the managed categories from the Categories card as {id, name}
     * objects (unique by name, non-empty, in defined order). The id is the
     * stable identifier (a real integer, or a temporary "new_*" token for
     * rows added in the browser and not yet saved).
     */
    function getManagedCategories() {
        var seen = {};
        var list = [];
        $('#tmfs-categories-list .tmfs-category-row').each(function() {
            var id = String($(this).find('.tmfs-category-id').val() || '');
            var name = ($(this).find('.tmfs-category-name').val() || '').trim();
            var k = name.toLowerCase();
            if (id && name && !seen[k]) {
                seen[k] = true;
                list.push({ id: id, name: name });
            }
        });
        return list;
    }

    /**
     * Look up a managed category name by its id (for building search text).
     */
    function categoryNameById(id) {
        var hit = null;
        getManagedCategories().forEach(function(c) {
            if (c.id === String(id)) {
                hit = c.name;
            }
        });
        return hit || '';
    }

    /**
     * Rebuild the toolbar category filter from the managed categories.
     * Option values are category IDs.
     */
    function rebuildCategoryUI() {
        var cats = getManagedCategories();

        var $filter = $('#tmfs-category-filter');
        var cur = String($filter.val() || '');
        $filter.empty();
        $filter.append($('<option>').val('').text(tmfsAdmin.i18n.allCategories));
        cats.forEach(function(c) {
            $filter.append($('<option>').val(c.id).text(c.name));
        });
        $filter.append($('<option>').val(UNCAT).text(tmfsAdmin.i18n.uncategorized));

        // Restore the previous selection if it still exists, else reset.
        if ($filter.find('option').filter(function() { return this.value === cur; }).length) {
            $filter.val(cur);
        } else {
            $filter.val('');
            categoryFilter = '';
        }
    }

    /**
     * Rebuild one template's category <select> from the managed categories,
     * preserving its current selection by ID. If the selected category was
     * removed, the select falls back to "no category" (the ID is gone).
     */
    function populateCardCategorySelect($select) {
        var cats = getManagedCategories();
        var cur = String($select.val() || '');
        var found = false;
        $select.empty();
        $select.append($('<option>').val('').text(tmfsAdmin.i18n.noCategory));
        cats.forEach(function(c) {
            $select.append($('<option>').val(c.id).text(c.name));
            if (c.id === cur) {
                found = true;
            }
        });
        $select.val(found ? cur : '');
    }

    /**
     * Sync the filter dropdown and every template category select after the
     * managed category list changes.
     */
    function refreshAllCategoryUI() {
        rebuildCategoryUI();
        getCards().each(function() {
            populateCardCategorySelect($(this).find('.tmfs-template-category'));
        });
    }

    /**
     * Get the next available template index.
     */
    function getNextIndex() {
        var max = -1;
        getCards().each(function() {
            var idx = parseInt($(this).data('index'), 10);
            if (idx > max) {
                max = idx;
            }
        });
        return max + 1;
    }

    /**
     * Get the current number of template cards.
     */
    function getTemplateCount() {
        return getCards().length;
    }

    /**
     * Rebuild the test email template dropdown from current cards.
     */
    function rebuildTestDropdown() {
        var $select = $('#stm_test_lang');
        var currentVal = $select.val();
        $select.empty();

        getCards().each(function(i) {
            var idx = $(this).data('index');
            var label = $(this).find('.tmfs-template-label').val();
            if (!label) {
                label = tmfsAdmin.i18n.templateLabel + ' #' + (i + 1);
            }
            $select.append($('<option>').val(idx).text(label));
        });

        // Restore selection if still exists
        if ($select.find('option[value="' + currentVal + '"]').length) {
            $select.val(currentVal);
        }
    }

    /**
     * Renumber the visible position indicator (#1, #2, …) on every card.
     */
    function renumberCards() {
        getCards().each(function(i) {
            $(this).find('.tmfs-template-num').first().text('#' + (i + 1));
        });
    }

    /**
     * Does a card pass the current category filter and search term?
     */
    function cardMatches($card) {
        var catId = String($card.find('.tmfs-template-category').val() || '');

        // Category filter (by ID).
        if (categoryFilter === UNCAT) {
            if (catId !== '') {
                return false;
            }
        } else if (categoryFilter) {
            if (catId !== categoryFilter) {
                return false;
            }
        }

        if (!searchTerm) {
            return true;
        }
        var hay = [
            $card.find('.tmfs-template-label').val(),
            catId ? categoryNameById(catId) : '',
            $card.find('.tmfs-template-locale').val(),
            $card.find('input[name*="[payment_link]"]').val(),
            $card.find('input[name*="[subject]"]').val(),
            $card.find('textarea[name*="[body]"]').val()
        ].join(' ').toLowerCase();
        return hay.indexOf(searchTerm.toLowerCase()) !== -1;
    }

    /**
     * Build the pagination controls.
     */
    function renderPagination(totalPages) {
        var $pag = $('#tmfs-templates-pagination');
        $pag.empty();
        if (totalPages <= 1) {
            return;
        }

        function pageBtn(label, page, opts) {
            opts = opts || {};
            var $b = $('<button type="button" class="button button-small tmfs-page-btn">')
                .text(label)
                .attr('data-page', page);
            if (opts.disabled) {
                $b.prop('disabled', true);
            }
            if (opts.current) {
                $b.addClass('button-primary');
            }
            return $b;
        }

        $pag.append(pageBtn(tmfsAdmin.i18n.prev, currentPage - 1, { disabled: currentPage <= 1 }));
        for (var p = 1; p <= totalPages; p++) {
            $pag.append(pageBtn(p, p, { current: p === currentPage }));
        }
        $pag.append(pageBtn(tmfsAdmin.i18n.next, currentPage + 1, { disabled: currentPage >= totalPages }));
    }

    /**
     * Apply search + pagination: show only the cards on the current page of
     * the filtered set, hide the rest. All inputs stay in the DOM so nothing
     * is lost on save.
     */
    function applyView(focusEl) {
        var matched = [];
        getCards().each(function() {
            if (cardMatches($(this))) {
                matched.push(this);
            } else {
                $(this).hide();
            }
        });

        var totalPages = Math.max(1, Math.ceil(matched.length / PER_PAGE));

        // Jump to the page that holds the card we want to keep in view.
        if (focusEl) {
            var fi = matched.indexOf(focusEl);
            if (fi >= 0) {
                currentPage = Math.floor(fi / PER_PAGE) + 1;
            }
        }

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        var start = (currentPage - 1) * PER_PAGE;
        var end = start + PER_PAGE;
        $(matched).each(function(i) {
            if (i >= start && i < end) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Count / no-results message.
        var total = getTemplateCount();
        var $count = $('#tmfs-templates-count');
        if (searchTerm) {
            if (matched.length === 0) {
                $count.text(tmfsAdmin.i18n.noResults);
            } else {
                $count.text(matched.length + ' / ' + total + ' ' + tmfsAdmin.i18n.templatesUnit);
            }
        } else {
            $count.text(total + ' ' + tmfsAdmin.i18n.templatesUnit);
        }

        renderPagination(totalPages);
        renumberCards();
    }

    // Collapse / expand a single card.
    $('#tmfs-templates-container').on('click', '.tmfs-template-toggle', function() {
        $(this).closest('.stm-template-card').toggleClass('stm-collapsed');
    });

    // Expand / collapse all.
    $('#tmfs-expand-all').on('click', function() {
        getCards().removeClass('stm-collapsed');
    });
    $('#tmfs-collapse-all').on('click', function() {
        getCards().addClass('stm-collapsed');
    });

    // Search.
    $('#tmfs-template-search').on('input', function() {
        searchTerm = $(this).val();
        currentPage = 1;
        applyView();
    });

    // Category filter.
    $('#tmfs-category-filter').on('change', function() {
        categoryFilter = $(this).val();
        currentPage = 1;
        applyView();
    });

    // Re-apply the view when a template's category changes (it may move in/out
    // of the active filter).
    $('#tmfs-templates-container').on('change', '.tmfs-template-category', function() {
        applyView();
    });

    // --- Category management ---

    // Add a category row with a temporary ID (PHP assigns a real one on save).
    $('#tmfs-add-category').on('click', function() {
        var idx = catRowSeq++;
        var html = $('#tmfs-category-row-tmpl').html()
            .replace(/__CATIDX__/g, idx)
            .replace(/__CATID__/g, 'new_' + idx);
        var $row = $(html);
        $('#tmfs-categories-list').append($row);
        $row.find('.tmfs-category-name').focus();
    });

    // Remove a category row.
    $('#tmfs-categories-list').on('click', '.tmfs-remove-category', function() {
        $(this).closest('.tmfs-category-row').remove();
        refreshAllCategoryUI();
        applyView();
    });

    // Keep the filter and all template selects in sync as category names change.
    $('#tmfs-categories-list').on('input', '.tmfs-category-name', function() {
        refreshAllCategoryUI();
    });

    // Reorder: move a card up / down in the overall list.
    $('#tmfs-templates-container').on('click', '.tmfs-move-up', function() {
        var $card = $(this).closest('.stm-template-card');
        var $prev = $card.prevAll('.stm-template-card').first();
        if ($prev.length) {
            $card.insertBefore($prev);
            applyView($card[0]);
            rebuildTestDropdown();
        }
    });
    $('#tmfs-templates-container').on('click', '.tmfs-move-down', function() {
        var $card = $(this).closest('.stm-template-card');
        var $next = $card.nextAll('.stm-template-card').first();
        if ($next.length) {
            $card.insertAfter($next);
            applyView($card[0]);
            rebuildTestDropdown();
        }
    });

    // Copy: duplicate a template (Payment Link ID is intentionally left blank
    // so the new template never collides with the source during detection).
    var COPY_FIELDS = ['label', 'locale', 'subject', 'body'];

    $('#tmfs-templates-container').on('click', '.tmfs-copy-template', function() {
        if (getTemplateCount() >= parseInt(tmfsAdmin.maxTemplates, 10)) {
            alert(tmfsAdmin.i18n.maxReached);
            return;
        }

        var $src = $(this).closest('.stm-template-card');
        var newIndex = getNextIndex();
        var $new = $($('#tmfs-template-tmpl').html().replace(/__INDEX__/g, newIndex));

        COPY_FIELDS.forEach(function(f) {
            var val = $src.find('[name$="[' + f + ']"]').val();
            $new.find('[name$="[' + f + ']"]').val(val);
        });

        // Category is a <select> that only holds the empty option until populated.
        var $newCat = $new.find('.tmfs-template-category');
        populateCardCategorySelect($newCat);
        $newCat.val(String($src.find('.tmfs-template-category').val() || ''));

        // Suffix the label so the duplicate is recognizable.
        var lbl = $src.find('.tmfs-template-label').val();
        $new.find('.tmfs-template-label').val(lbl ? lbl + tmfsAdmin.i18n.copySuffix : '');

        $new.insertAfter($src);
        rebuildTestDropdown();
        applyView($new[0]);
        $new.find('.tmfs-template-label').focus();
    });

    // Pagination clicks.
    $('#tmfs-templates-pagination').on('click', '.tmfs-page-btn', function() {
        var page = parseInt($(this).attr('data-page'), 10);
        if (isNaN(page)) {
            return;
        }
        currentPage = page;
        applyView();
        $('#tmfs-template-search')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Add template
    $('#tmfs-add-template').on('click', function() {
        if (getTemplateCount() >= parseInt(tmfsAdmin.maxTemplates, 10)) {
            alert(tmfsAdmin.i18n.maxReached);
            return;
        }

        var newIndex = getNextIndex();
        var tmpl = $('#tmfs-template-tmpl').html().replace(/__INDEX__/g, newIndex);

        var $new = $(tmpl);
        $('#tmfs-templates-container').append($new);
        populateCardCategorySelect($new.find('.tmfs-template-category'));

        // Clear search/category filter so the new (empty, expanded) card is visible.
        searchTerm = '';
        $('#tmfs-template-search').val('');
        categoryFilter = '';
        $('#tmfs-category-filter').val('');
        applyView($new[0]);
        rebuildTestDropdown();

        $new.find('.tmfs-template-label').focus();
    });

    // Delete template
    $('#tmfs-templates-container').on('click', '.tmfs-delete-template', function() {
        if (getTemplateCount() <= 1) {
            alert(tmfsAdmin.i18n.cannotDeleteAll);
            return;
        }
        if (!confirm(tmfsAdmin.i18n.confirmDelete)) {
            return;
        }
        $(this).closest('.stm-template-card').remove();
        applyView();
        rebuildTestDropdown();
    });

    // Reset individual template to defaults
    $('#tmfs-templates-container').on('click', '.tmfs-reset-template', function() {
        if (!confirm(tmfsAdmin.i18n.confirmResetTemplate)) {
            return;
        }

        var $card = $(this).closest('.stm-template-card');
        var position = getCards().index($card);
        var defaults = tmfsAdmin.defaultTemplates;
        var tmpl = (position < defaults.length) ? defaults[position] : tmfsAdmin.emptyTemplate;

        $card.find('.tmfs-template-label').val(tmpl.label);
        $card.find('.tmfs-template-locale').val(tmpl.locale);
        $card.find('input[name*="[payment_link]"]').val(tmpl.payment_link);
        $card.find('input[name*="[subject]"]').val(tmpl.subject);
        $card.find('.tmfs-field-body').val(tmpl.body);

        rebuildTestDropdown();
        applyView($card[0]);
    });

    // Sync label changes to test dropdown
    $('#tmfs-templates-container').on('input', '.tmfs-template-label', function() {
        rebuildTestDropdown();
    });

    // Initial render.
    rebuildCategoryUI();
    applyView();
});
