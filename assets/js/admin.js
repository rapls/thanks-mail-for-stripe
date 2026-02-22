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

    function getNextIndex() {
        var max = -1;
        $('.stm-template-card').each(function() {
            var idx = parseInt($(this).data('index'), 10);
            if (idx > max) {
                max = idx;
            }
        });
        return max + 1;
    }

    function getTemplateCount() {
        return $('.stm-template-card').length;
    }

    function rebuildTestDropdown() {
        var $select = $('#stm_test_lang');
        var currentVal = $select.val();
        $select.empty();

        $('.stm-template-card').each(function() {
            var idx = $(this).data('index');
            var label = $(this).find('.tmfs-template-label').val();
            if (!label) {
                label = tmfsAdmin.i18n.templateLabel + ' #' + ($(this).index() + 1);
            }
            $select.append('<option value="' + idx + '">' + $('<span>').text(label).html() + '</option>');
        });

        if ($select.find('option[value="' + currentVal + '"]').length) {
            $select.val(currentVal);
        }
    }

    // Add template
    $('#tmfs-add-template').on('click', function() {
        if (getTemplateCount() >= tmfsAdmin.maxTemplates) {
            alert(tmfsAdmin.i18n.maxReached);
            return;
        }

        var nextIndex = getNextIndex();
        var tmpl = $('#tmfs-template-tmpl').html().replace(/__INDEX__/g, nextIndex);
        $('#tmfs-templates-container').append(tmpl);
        rebuildTestDropdown();
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
        rebuildTestDropdown();
    });

    // Reset individual template to defaults
    $('#tmfs-templates-container').on('click', '.tmfs-reset-template', function() {
        if (!confirm(tmfsAdmin.i18n.confirmResetTemplate)) {
            return;
        }

        var $card = $(this).closest('.stm-template-card');
        var position = $('#tmfs-templates-container .stm-template-card').index($card);
        var defaults = tmfsAdmin.defaultTemplates;
        var tmpl = (position < defaults.length) ? defaults[position] : tmfsAdmin.emptyTemplate;

        $card.find('.tmfs-template-label').val(tmpl.label);
        $card.find('select').val(tmpl.locale);
        $card.find('input[name*="[payment_link]"]').val(tmpl.payment_link);
        $card.find('input[name*="[subject]"]').val(tmpl.subject);
        $card.find('.tmfs-field-body').val(tmpl.body);

        rebuildTestDropdown();
    });

    // Sync label changes to test dropdown
    $('#tmfs-templates-container').on('input', '.tmfs-template-label', function() {
        rebuildTestDropdown();
    });
});
