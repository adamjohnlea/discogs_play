<?php
include_once 'utils/utils.php';
include_once 'funcs.php';

// IF THIS IS A SINGLE RELEASE VIEW, GET INFORMATION FROM RELEASE AND FROM USER COLLECTION FOR THAT RELEASE
if (isset($release_id)) {
    if ($release_id) {
        //get_release_information($release_id);
        $releaseinfo = get_release_information($release_id);
        $myreleaseinfo = get_my_release_information($release_id);

        // IF NOT A SINGLE RELEASE VIEW, GET DATA FOR USER'S COLLECTION TO DISPLAY COVER GALLERY.
    } else {
        // PULL DISCOGS DATA REGARDING MY COLLECTION
        $collection = get_collection();
    }
}
?>

<!-- Header -->
<?php include_once 'views/header.php'; ?>
<!-- End -->

<!-- Header Banner -->
<?php include_once 'views/top_banner.php'; ?>
<!-- End -->

<!-- Header Navigation/Filter Bar -->
<?php include_once 'views/top_nav_filter_bar.php'; ?>
<!-- End -->

<!-- Gallery of Releases -->
<?php include_once 'views/release_gallery.php'; ?>
<!-- End -->

<!-- Footer -->
<?php include_once 'views/footer.php'; ?>
<!-- End -->