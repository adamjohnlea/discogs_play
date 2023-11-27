<?php
function wrap_table_rows($title,$rows) {

    $table = '<!-- START ' . $title . ' -->' . "\n"
        . '<div class="p-1 table-responsive">' . "\n"
        . '<table class="table table-striped table-bordered">' . "\n"
        . '<tbody>';

    if ( $title ) :
        $table = $table . '<tr><th scope="row" colspan="3" style="width:1%">' . $title . '</th></tr>' . "\n";
    endif;

    $table = $table . $rows;

    $table = $table
        . '</tbody>' . "\n"
        . '</table>' . "\n"
        . '</div> <!-- END ' . $title . ' -->' . "\n";

    return $table;

}