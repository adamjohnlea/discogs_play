<?php
function display_gallery_item($release) {

    $artists = implode(", ", array_column($release['basic_information']['artists'], "name"));
    $title = $release['basic_information']['title'];
    $id = $release['basic_information']['id'];
    $imageupdatedtext = '';
    $imagefile = './img/' . $release["basic_information"]["id"] . 'jpeg';
    if ( !file_exists($imagefile) && is_dir( "./img/" ) ):
        $imageupdatedtext = "Missing file has been downloaded from Discogs server.";
        $imagename = file_get_contents($release['basic_information']['cover_image']);
        file_put_contents($imagefile, $imagename);
    elseif (!file_exists($imagefile) && !is_dir( "./img/" ) ):
        $imageupdatedtext = "Missing file has been hotlinked from Discogs server.";
        $imagefile = $release['basic_information']['cover_image'];
    endif;

    $adddate = date('m/d/y', strtotime(substr($release['date_added'],0,10)));
    $is_new_badge ='';
    if(strtotime($adddate) > strtotime('-14 days')) :
        $is_new_badge = '<span class="badge rounded-pill bg-success">Newly Added</span>';
    endif;
    ?>

    <!-- Gallery item -->
    <div class="col-xl-3 col-md-6 col-sm-6 my-3">
        <div class="card h-100">


            <a href="/?releaseid=<?php echo $id ?>">
                <img class="card-img-top rounded p-2" src="<?php echo $imagefile; ?>" alt="<?php echo $title; ?>">
            </a>

            <div class="card-body d-flex flex-column">
                <?php if ( $imageupdatedtext ) : ?>
                    <p class="alert alert-warning" role="alert">
                        <small class="text-muted text-center"><?php echo $imageupdatedtext; ?></small>
                    </p>
                <?php endif; ?>
                <div class="d-flex flex-column mt-auto">
                    <h5 class="card-title"><i class="fa-solid fa-quote-right text-muted"></i> <?php echo $title; ?></h5>
                    <h6 class="card-title"><i class="fa-solid fa-people-group text-muted"></i> <?php echo $artists; ?></h6>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between"><small>added <?php echo $adddate; ?></small>
                <?php echo $is_new_badge; ?>
            </div>

        </div>

    </div>

    <!-- End gallery Item -->
<?php } // End display_gallery_item() ?>


