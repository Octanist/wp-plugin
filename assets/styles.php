<?php
function admin_styles()
{
    ?>
    <style>
        .toplevel_page_octanist-settings div img {
            padding: 6px 0 0!important;
        }
    </style>
    <?php
}
add_action('admin_head', 'admin_styles', 0);