<?php
function wrap_accordian_rows($header, $data, $open=0) {

    $accordian = '<!-- START ' . $header . ' -->' . "\n"
        . '<div class="accordion-item">' . "\n"
        . '<h2 class="accordion-header font-weight-bold" id="heading'
        . $header
        . '">' . "\n"
        . '<button class="accordion-button';

    if ( $open ) :
        $accordian = $accordian . '';
    else:
        $accordian = $accordian . ' collapsed';
    endif;

    $accordian = $accordian
        . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse'
        . $header
        . '" aria-expanded="false" aria-controls="collapse'
        . $header
        . '"><strong>'
        . $header
        . '</strong></button></h2>' . "\n"
        . '<div id="collapse'
        . $header
        . '" class="accordion-collapse collapse';

    if ( $open ) :
        $accordian = $accordian. ' show';
    endif;

    $accordian = $accordian
        . '" aria-labelledby="heading'
        . $header
        . '">' . "\n"
        . '<div class="accordion-body">' . "\n";

    $accordian = $accordian . $data;

    $accordian = $accordian
        . ' </div>' . "\n"
        . '</div>' . "\n"
        . '</div>' . "\n"
        . '<!-- END ' . $header . ' -->' . "\n";

    return $accordian;
}