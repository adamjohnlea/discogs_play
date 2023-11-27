<!-- Pagination / Nav / Filter Bar-->
<div class="btn-toolbar d-flex justify-content-center p-3" role="toolbar" aria-label="Toolbar with button groups">

    <div class="btn-group btn-group-sm mr-2 p-1" role="group" aria-label="Pagination">
        <?php if (isset($release_id)){
        if (!$release_id) { ?>
        <a class="btn btn-primary text-uppercase <?php if (isset($page)) {
            if ($page == 1) echo "disabled";
        } ?>" href="/?folder_id=<?php if (isset($folder_id)) {
            echo $folder_id;
        } ?>&sort_by=<?php if (isset($sort_by)) {
            echo $sort_by;
        } ?>&order=<?php if (isset($order)) {
            echo $order;
        } ?>&per_page=<?php if (isset($per_page)) {
            echo $per_page;
        } ?>&page=<?php if ($page != 1) echo(intval($page) - 1); ?>" tabindex="-1">&#12298;</a>
        <?php
        $x = 1;
        if (isset($collection)) {
            $pages = $collection['pagination']['pages'];
        }
        while ($x <= $pages) {
            ?>
            <a class="btn btn-primary text-uppercase <?php if ($page == $x) echo "active disabled"; ?>"
               href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $x; ?>"><?php echo $x; ?></a>
            <?php $x++;
        } ?>

        <a class="btn btn-primary text-uppercase <?php if ($page == $collection['pagination']['pages']) echo "disabled"; ?>"
           href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=<?php echo $per_page; ?>&page=<?php if ($page != $pages) echo(intval($page) + 1); ?>"
           tabindex="-1">&#12299;</a>
    </div>

    <div class="btn-group btn-group-sm mr-2 p-1" role="group" aria-label="Per Page">
        <button id="btnGroupDrop1" type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <?php echo $per_page; ?> Per Page
        </button>
        <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item"
               href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=25&page=1">25</a>
            <a class="dropdown-item"
               href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=50&page=1">50</a>
            <a class="dropdown-item"
               href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=100&page=1">100</a>
        </div>

        <?php } else { ?>
            <button type="button" class="btn btn-primary text-uppercase" onclick="history.go(-1)">Back to the Collection</button>
        <?php }
        } ?>
    </div>

    <?php if (isset($release_id)){
    if (!$release_id) { ?>
    <div class="btn-group btn-group-sm mr-2 p-1" role="group" aria-label="Folder Navigation">
        <?php if (isset($folders)) {
            foreach ($folders['folders'] as $folder) {

                $folderid = $folder['id'];
                $foldername = $folder['name'];
                $foldercount = $folder['count'];

                if ($foldercount >= 1) { ?>
                    <a href="/?folder_id=<?php echo $folderid; ?>&sort_by=<?php echo $sort_by; ?>&order=<?php echo $order; ?>&per_page=<?php echo $per_page; ?>&page=1"
                       title="View Folder '<?php echo $foldername; ?>'"
                       class="btn btn-primary text-uppercase<?php if ($folder_id == $folderid) echo " disabled"; ?>"><?php echo $foldername; ?>
                        (<?php echo $foldercount; ?>)</a>
                <?php }
            }
        } ?>
    </div>
    <?php } } ?>


    <?php if (!$release_id) { ?>
        <div class="btn-group btn-group-sm mr-2 p-1" role="group" aria-label="Sort by Artist or Date Added">
            <a href="/?folder_id=<?php echo $folder_id; ?>&sort_by=added&order=<?php echo $order; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page; ?>"
               title="Sort By Added"
               class="btn btn-info text-uppercase<?php if ($sort_by == "added") echo " disabled"; ?>"><i
                    class="fa-solid fa-clock"></i></A>
            <a href="/?folder_id=<?php echo $folder_id; ?>&sort_by=artist&order=<?php echo $order; ?>&page=<?php echo $page; ?>"
               title="Sort By Artist"
               class="btn btn-info text-uppercase<?php if ($sort_by == "artist") echo " disabled"; ?>"><i
                    class="fa-solid fa-user-group"></i></a>
        </div>

        <div class="btn-group btn-group-sm  p-1" role="group" aria-label="Ascending or Descending">
            <a href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=asc&per_page=<?php echo $per_page; ?>"
               title="Ascending"
               class="btn btn-secondary text-uppercase<?php if ($order == "asc") echo " disabled"; ?>"><i
                    class="fa-solid fa-circle-arrow-down"></i></a>
            <a href="/?folder_id=<?php echo $folder_id; ?>&sort_by=<?php echo $sort_by; ?>&order=desc&per_page=<?php echo $per_page; ?>&page=<?php echo $page; ?>"
               title="Descending"
               class="btn btn-secondary text-uppercase<?php if ($order == "desc") echo " disabled"; ?>"><i
                    class="fa-solid fa-circle-arrow-up"></i></a>
        </div>

    <?php } ?>

</div> <!-- Pagination / Nav / Filter Bar-->
