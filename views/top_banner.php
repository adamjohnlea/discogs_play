<div class="row">
    <div class="col-12 mx-auto my-1">
        <div class="text-white p-3 shadow-sm rounded banner">
            <?php if (!str_contains($_SERVER['REQUEST_URI'], "releaseid")){ ?>
            <h2 class="display-6">My Discogs Collection</h2>
            <?php if (isset($DISCOGS_USERNAME)) { echo "<p>Discogs Username: <a href='https://www.discogs.com/user/" . $DISCOGS_USERNAME . "'>" . $DISCOGS_USERNAME . "</a></p>"; } ?>
            <?php } ?>

            <?php if (isset($release_id)) {
                if ($release_id):
                    get_release_information($release_id); ?>

                    <h2 class="display-6">
                        "<?php if (isset($releaseinfo)) {
                            echo $releaseinfo['title'];
                        } ?>"
                        by <?php echo implode(", ", array_column($releaseinfo['artists'], "name")); ?>
                    </h2>
                <?php else: ?>
                    <p class="lead">
                        <b><?php if (isset($current_folder_name)) {
                                echo $current_folder_name;
                            } ?></b> (<?php if (isset($current_folder_count)) {
                            echo $current_folder_count;
                        } ?> items) Sorted by: <?php if (isset($sort_by)) {
                            echo $sort_by;
                        } ?>, <?php if (isset($order)) {
                            echo $order;
                        } ?>ending
                    </p>
                <?php endif;
            } ?>

        </div>
    </div>
</div>
