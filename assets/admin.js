/* EDIT. SEO Fix — Admin JS */
jQuery(function ($) {

    // Fix robots.txt button
    $('#edit-seo-fix-robots').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $result = $('#edit-seo-fix-robots-result');
        $btn.prop('disabled', true).text('Fixing...');
        $result.text('');

        $.get(ajaxurl, { action: 'edit_seo_get_robots_nonce' }, function (nonceRes) {
            if (!nonceRes.success) {
                $result.css('color', 'red').text('Failed to get nonce.');
                $btn.prop('disabled', false).text('Fix robots.txt (remove duplicate)');
                return;
            }
            $.post(ajaxurl, {
                action: 'edit_seo_write_robots',
                nonce: nonceRes.data
            }, function (res) {
                if (res.success) {
                    $result.css('color', 'green').text('Done — robots.txt updated.');
                } else {
                    $result.css('color', 'red').text('Error: ' + res.data);
                }
                $btn.prop('disabled', false).text('Fix robots.txt (remove duplicate)');
            });
        });
    });

    var mediaUploader;

    $('#edit-seo-pick-image').on('click', function (e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Select Default OG Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#default_og_image_id').val(attachment.id);
            var preview = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            $('#edit-seo-og-preview').remove();
            $('#edit-seo-pick-image').after(
                '<img src="' + preview + '" style="max-width:120px;margin-top:8px;" id="edit-seo-og-preview">'
            );
        });

        mediaUploader.open();
    });
});
