<?php
function wrap_listgroup_items($groupname,$items) {

    $table = '<!-- START ' . $groupname . ' -->' . "\n"
        . '<ul class="list-group striped-list">' . "\n";

    if ( $groupname ) :
        $table = $table . '<li class="list-group-item striped-list">' . $groupname . '</li>' . "\n";
    endif;

    $table = $table . $items;

    $table = $table
        . '</ul> <!-- END ' . $groupname . ' -->' . "\n";

    return $table;

}