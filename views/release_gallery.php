<div class="row">
    <?php if ($release_id) {
        display_release_data($release_id);
    } else {
        foreach ($collection['releases'] as $release) {
            display_gallery_item($release);
        }
    }
    ?>
</div>
