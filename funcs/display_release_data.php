<?php
function display_release_data($release_id) {
    global $releaseinfo;
    global $myreleaseinfo;

    $id = $releaseinfo['id'];
    $resource_url = $releaseinfo['resource_url'];

    $labelname = '';
    if( array_key_exists('labels', $releaseinfo) ) :
        $number_of_labels = sizeof($releaseinfo['labels']);
        for( $i=0; $i<$number_of_labels;$i++ ) :
            if( array_key_exists('name', $releaseinfo['labels'][$i]) )
                $labelname = $labelname
                    . $releaseinfo['labels'][$i]['name'];
            if( array_key_exists('catno', $releaseinfo['labels'][$i]) )
                $labelname = $labelname
                    . ', ' . $releaseinfo['labels'][$i]['catno']
                    . '<br/>';
        endfor;
    endif;
    $formats = '';
    if( array_key_exists('formats', $releaseinfo) ) :
        $number_of_formats = sizeof($releaseinfo['formats']);
        for($i=0; $i<$number_of_formats;$i++) :
            if( array_key_exists('name', $releaseinfo['formats'][$i]) ) :
                $qty = '';
                if ( $releaseinfo['formats'][$i]['qty'] > 1 )
                    $qty = $releaseinfo['formats'][$i]['qty'] . ' x ';
                $formats = $formats
                    . $qty
                    . '<b>'
                    . $releaseinfo['formats'][$i]['name']
                    . '</b>';
            endif;
            if( !array_key_exists( 'text', $releaseinfo['formats'][$i]) && !array_key_exists('descriptions', $releaseinfo['formats'][$i]) )
                $formats = $formats
                    . '<br/>';
            if( array_key_exists('descriptions', $releaseinfo['formats'][$i]) )
                $formats = $formats
                    . ', ' . implode(", ", $releaseinfo['formats'][$i]['descriptions']);
            if( !array_key_exists('descriptions', $releaseinfo['formats'][$i]) )
                $formats = $formats
                    . '<br/>';
            if( array_key_exists('text', $releaseinfo['formats'][$i]) )
                $formats = $formats
                    . ', <i>' . $releaseinfo['formats'][$i]['text'] . '</i>'
                    . '<br/>';
        endfor;
    endif;

    $genres = implode(", ", $releaseinfo['genres']);

    $styles = "";
    if( array_key_exists('styles', $releaseinfo) )
        $styles = implode(", ", $releaseinfo['styles']);

    $title = $releaseinfo["title"];
    $artists = implode(", ", array_column($releaseinfo['artists'], "name"));

    $identifier_rows = '';
    if( array_key_exists('identifiers', $releaseinfo) ) :
        $identifiers = $releaseinfo['identifiers'];
        $number_of_identifiers = sizeof($identifiers);
        $identifier_rows = '';
        for( $i=0; $i<$number_of_identifiers;$i++ ) :
            $identifier_type = '';
            $identifier_value = '';
            $identifier_description = '';
            $identifier_type = $identifiers[$i]['type'];
            $identifier_value = $identifiers[$i]['value'];
            if ( isset($identifiers[$i]['description']) )
                $identifier_description = $identifiers[$i]['description'];

            $identifier_rows = $identifier_rows . '<tr><td data-align="left">'
                . $identifier_type
                . '</td><td>'
                . $identifier_value
                . '</td><td>'
                . @$identifier_description
                . '</td></tr>
			';

        endfor;
    endif;

    $series = $releaseinfo['series'];
    if( !empty($series )) :
        $series = $series[0]['name'];
    else:
        $series = '';
    endif;

    $list_of_companies_rows = '';
    if( array_key_exists('companies', $releaseinfo) ) :
        $companies = $releaseinfo['companies'];
        for($i=0; $i<sizeof($companies);$i++) :
            $list_of_companies_rows = $list_of_companies_rows . '<li class="list-group-item"><strong>'
                . $companies[$i]['entity_type_name']
                . '</strong> '
                . $companies[$i]['name']
                . '</li>
						';
        endfor;
    endif;



    $releasenotes = '';
    if( array_key_exists('notes', $releaseinfo) )
        $releasenotes = $releaseinfo['notes'];
    $images = $releaseinfo['images'];
    $year = '?';
    if( array_key_exists('released', $releaseinfo) )
        $year = $releaseinfo['released'];

    $my_release_notes_rows = '';
    if( array_key_exists('notes', $myreleaseinfo['releases'][0]) ) :
        foreach ($myreleaseinfo['releases'][0]['notes'] as $mynotes) :
            if ( $mynotes['field_id'] == 1 ):
                $noteicon = 'fa-compact-disc';
                $notetype = 'Media';
            elseif ( $mynotes['field_id'] == 2 ):
                $noteicon = 'fa-square-full';
                $notetype = 'Jacket';
            elseif ( $mynotes['field_id'] == 3 ):
                $noteicon = 'fa-clipboard';
                $notetype = 'Notes';
            endif;

            $my_release_notes_rows = $my_release_notes_rows
                . '<tr><th><i class="fa-fw fa-solid '
                . $noteicon
                . '"></i></th><td>'
                . $notetype
                . '</td><td>'
                . $mynotes['value']
                .'</td></tr>
			';
        endforeach;
    endif;

    $release_tracklist_rows = '';
    $track_extraartists_list = '';
    if( array_key_exists('tracklist', $releaseinfo) ) :
        $tracklist = $releaseinfo['tracklist'];
        $number_of_release_tracklist_tracks = sizeof($tracklist);
        $release_tracklist_rows = '<tr><th data-align="left" style="width:1%">#</th><td>Track Name</td><td>m:s</td></tr>';
        for($i=0; $i<$number_of_release_tracklist_tracks;$i++)  :
            if( array_key_exists('extraartists', $releaseinfo['tracklist'][$i]) ) :
                $track_extraartists = $tracklist[$i]['extraartists'];
                $number_of_track_extraartists = sizeof($track_extraartists);
                $track_extraartists_list = '';
                for($e=0; $e<$number_of_track_extraartists; $e++) :
                    $track_extraartists_list = $track_extraartists_list
                        . $releaseinfo['tracklist'][$i]['extraartists'][$e]['role']
                        . ' '
                        . '<strong>'
                        . $releaseinfo['tracklist'][$i]['extraartists'][$e]['name']
                        . '</strong>';
                    if ( $e != ($number_of_track_extraartists - 1) ) :
                        $track_extraartists_list = $track_extraartists_list
                            . ', ';
                    endif;

                endfor;
            endif;
            $release_tracklist_rows = $release_tracklist_rows
                . '<tr><th data-align="left" style="width:1%">'
                . $tracklist[$i]['position']
                . ":  "
                . '</th><td data-align="left">'
                .  $tracklist[$i]['title']
                . '<br/>'
                . $track_extraartists_list
                .  '</td><td data-align="left">'
                . $tracklist[$i]['duration']
                . '</td></tr>
		';
            $track_extraartists_list = '';
        endfor;
    endif;

    $extra_artists_rows = '';
    if( array_key_exists('extraartists', $releaseinfo) ) :
        $extraartists = $releaseinfo['extraartists'];
        $number_of_extra_artists = sizeof($extraartists);
        for($i=0; $i<$number_of_extra_artists;$i++) :
            $artist_role = $extraartists[$i]['role'];
            $artist_name = $extraartists[$i]['name'];
            $artist_tracks = $extraartists[$i]['tracks'];
            if ( $artist_tracks)
                $artist_tracks = ' (' . $artist_tracks . ')';

            $extra_artists_rows = $extra_artists_rows
                . '<li class="list-group-item"><strong>'
                . $artist_role
                . ':</strong> '
                . $artist_name
                . $artist_tracks
                . '</li>
		';
        endfor;
    endif;
    ?>

    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
        <div class="bg-white rounded shadow-sm">

            <div class="card h-100">
                <div id="carouselExampleControls" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner">
                        <?php for($i=0; $i<sizeof($images);$i++) {
                            echo '<div class="carousel-item';
                            if($i == 0) {
                                echo " active";
                            }
                            echo '"><img class="d-block w-100" src="'
                                . $images[$i]['resource_url']
                                . '" alt="'
                                . $images[$i]['type']
                                . '"></div>
			'; } ?>
                    </div>

                    <button class="carousel-control-prev" href="#carouselExampleControls" role="button" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>

                    <button class="carousel-control-next" href="#carouselExampleControls" role="button" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>

                </div> <!-- END carouselExampleControls -->

                <div class="card-body">
                    <div class="p-1 table-responsive">
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <th scope="row" style="width:1%"><i class="fa-fw fa-solid fa-quote-right"></i></th>
                                <td style="width:1%">Title</td>
                                <td><?php echo $title; ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-people-group"></i></th>
                                <td>Artist</td>
                                <td><?php echo $artists ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-calendar-days"></i></th>
                                <td>Released</td>
                                <td><?php echo $year; ?></td>
                            </tr>
                            <?php if (!empty($series)) : ?>
                                <tr>
                                    <th scope="row"><i class="fa-fw fa-solid fa-calendar-days"></i></th>
                                    <td>Series</td>
                                    <td><?php echo $series; ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-building"></i></th>
                                <td>Label</td>
                                <td><?php  if( $labelname ) echo $labelname; ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-compact-disc"></i></th>
                                <td>Format</td>
                                <td>
                                    <?php echo $formats; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-bars-staggered"></i></th>
                                <td>Genres</td>
                                <td>
                                    <?php echo $genres; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><i class="fa-fw fa-solid fa-bars-staggered"></i></th>
                                <td>Styles</td>
                                <td>
                                    <?php if( $styles ) echo $styles; ?>
                                </td>
                            </tr>


                            <?php //Release notes are generated as complete rows with headers.
                            echo $my_release_notes_rows;
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div> <!-- END card body -->

                <div class="card-footer d-flex justify-content-between">
                    <a class="btn btn-secondary btn-sm" href="https://www.discogs.com/release/<?php echo $id ?>">Discogs <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    <a class="btn btn-secondary btn-sm" href="<?php echo $resource_url; ?>">JSON <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>

            </div>
            <?php
            $url = str_replace(' ', '+','https://itunes.apple.com/search?term='. $artists . "+" . $title . '&media=music&explicit=Y&entity=album');
            $data = file_get_contents($url);
            $characters = json_decode($data);
            if (isset($characters->results[0]->collectionId)) {
            $albumId = $characters->results[0]->collectionId;
            $albumArtist = $characters->results[0]->artistName;
            if(isset($albumId)) var_dump($url); {

                    $albumName = $title; // replace with album name to test
                    $collectionId = null;

                    $jsonData = json_decode($data);
                    $results = $jsonData->results;

                    foreach ($results as $result) {
                        if (strtolower($result->collectionName) == strtolower($albumName)) {
                            $collectionId = $result->collectionId;
                            break;
                        }
                    }

                    if ($collectionId !== null) {
                        echo "The collection ID for the album '$albumName' is $collectionId.";
                    } else {
                        echo "No album found with the name '$albumName'.";
                    }
                ?>

                <div class="card-footer d-flex justify-content-between">
                    <iframe allow="autoplay *; encrypted-media *;" frameborder="0" height="450" style="width:100%;max-width:660px;overflow:hidden;background:transparent;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="https://embed.music.apple.com/us/album/<?php  echo $albumArtist ?>/<?php  echo strval($collectionId); ?>"></iframe>
                </div>
            <?php } } ?>
        </div>
    </div>

    <div class="col-xl-8 col-lg-6 col-md-6 mb-4">
        <div class="bg-white rounded shadow-sm">

            <div class="accordion" id="accordionExample">

                <?php if ( isset($releasenotes) && ($releasenotes != '') ) :
                    echo wrap_accordian_rows('Notes',wrap_table_rows('', '<tr><td>' . $releasenotes. '</td></tr>'),'opened');
                endif; ?>
                <?php echo wrap_accordian_rows('TrackList',wrap_table_rows('',$release_tracklist_rows),'opened'); ?>
                <?php echo wrap_accordian_rows('Credits',wrap_listgroup_items('',$extra_artists_rows)); ?>
                <?php echo wrap_accordian_rows('Companies',wrap_listgroup_items('',$list_of_companies_rows)); ?>
                <?php echo wrap_accordian_rows('Identifiers',wrap_table_rows('',$identifier_rows)); ?>

            </div>

        </div>

    </div>
    <?php } ?>
