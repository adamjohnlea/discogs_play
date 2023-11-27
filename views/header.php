<!DOCTYPE html>
<html lang="en">
<head>

    <title><?php if (isset($releaseinfo)) {
            echo $releaseinfo["title"] . " by " . $releaseinfo["artists"][0]["name"] . " | ";
        } ?>My Discogs Collection</title>
    <meta name="viewport" content="width=device-width, initial-scale=.8">

    <script src="https://kit.fontawesome.com/7e1a0bb728.js" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" ></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-dark-5@1.1.3/dist/css/bootstrap-night.min.css" rel="stylesheet">

    <style>
    </style>

</head>

<body>

<div class="container-fluid">
