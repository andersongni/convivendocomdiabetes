// Do the things when document is ready
jQuery(document).ready(function($)
{
    // Initialize needed variable
    var apt_media_frame;

    // Handle default featured image's selector's click
    $('#default_featured_image_selector').click(function(e)
    {
        // Open custom media frame if available
        if (apt_media_frame)
        {
            apt_media_frame.open();
            return;
        }

        // Clone default media manager
        apt_media_frame = wp.media.frames.apt_media_frame = wp.media({
            library: {
                type: 'image'
            }
        });

        // Handle media selection
        apt_media_frame.on('select', function()
        {
            var media_attachment = apt_media_frame.state().get('selection').first().toJSON();
            $('#default_featured_image').val(media_attachment.url);
            $('#default_featured_image').attr("data-changed","true");
        });

        // Open custom media frame
        apt_media_frame.open();
        return false;
    });
});