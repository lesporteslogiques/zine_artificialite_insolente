#!/usr/bin/php -q
<?php

/*
   Montage des zines pour Artificialité Insolente
   Certaines pages sont créées en piochant des images dans un répertoire
   Puis elles sont assemblées dans un second temps par un script bash
    en utilisant convert (imagemagick) sous forme de pages comprenant 2 images A5 (format A4 paysage)
   Ces pages sont ensuite assemblées dans un unique fichier pdf
   Le pdf est à imprimer et massicoter

  13-14 sept. 2021 / pierre@lesporteslogiques.net
  PHP 7.0.33 / Debian 9.5 @ kirin

  Chaque exemplaire est caractérisé par un timestamp unique AAAA-MM-JJ_HH:MM:SS
  3 fichiers pour chaque exemplaire
    * version print : les pages sont assemblées pour l'impression
    * version web : les pages sont dans l'ordre
    * chemin de fer : toutes les pages en miniature

  DEMARRER :
    * dans un terminal : php ./montage_zine_ai.php --exemplaires=10
  COMMENT IMPRIMER ?
    * paysage, recto-verso, bord court
    * massicoter en suivant les traits de coupe

  nb : le fichier chemin de fer est rudimentaire et améliorable!
*/

$OK = true;                             // Permet de tester le script "à blanc"
$usleep = 1000000;                      // pause en microsecondes


// Paramètres des documents **************************************************
// toutes les pages seront redimensionnées à la définition fournie

$nom_zine = "zine_ai";
$total_pages = 32; // Toujours un multiple de 4 : 8, 12, 16, 20, 24, 32, 36, etc.
$ph = 877;               // définition HORIZONTALE en pixels
$pv = 1240;              // définition VERTICALE en pixels
$densite = "150";        // dpi
$rep_pages = "pages";    // répertoires des pages originales
$rep_ex = "exemplaires"; // répertoire de traitement et de sortie
$exemplaires = 1;        // par défaut, si pas défini en argument de commande

// Définir les pages statiques ***********************************************

$pages_statiques = array(
    "page_02.png" => "page_couv_int.png",
    "page_03.png" => "page_collage_6.png",
    "page_05.png" => "gwel_p1.png",
    "page_06.png" => "page_collage_5.png",
    "page_07.png" => "page_ai_chat.png",
    "page_08.png" => "page_kinescope_note.png",
    "page_09.png" => "page_julbel2.png",
    "page_10.png" => "page_digital_labour.png",
    "page_11.png" => "page_natalie.png",
    "page_13.png" => "page_anais_1.png",
    "page_14.png" => "page_anais_2.png",
    "page_15.png" => "page_anais_3.png",
    "page_16.png" => "page_anais_4.png",
    "page_17.png" => "page_ai_cafe.png",
    "page_18.png" => "page_julbel1.png",
    "page_19.png" => "page_discours.png",
    "page_20.png" => "page_collage_4.png",
    "page_21.png" => "gwel_p2.png",
    "page_22.png" => "page_collage_2.png",
    "page_23.png" => "biais_ia.png",
    "page_25.png" => "gwel_p3.png",
    "page_26.png" => "page_collage_3.png",
    "page_27.png" => "page_vqgan.png",
    "page_28.png" => "page_collage_1.png",
    "page_29.png" => "page_guru4.png",
    "page_30.png" => "page_fiction.png",
    "page_31.png" => "chez_le_meme_editeur.png",
    "page_32.png" => "page_ai_aie.png",
);


// définir les pages multiples ***********************************************
// Certaines pages affichent une image parmi un répertoire
// Ces images varient d'un exemplaire à l'autre selon un modulo


$pages_dynamiques = array(
    "page_01.png" => "page_couverture",
    "page_04.png" => "page_automobile",
    "page_12.png" => "page_oeil",
    "page_24.png" => "page_vqgan1",
);


/*
  Fonction pour récupérer des arguments au lancement du script
  sous la forme : php myscript.php --user=nobody --password=secret -p --access="host=127.0.0.1 port=456"
  d'après https://www.php.net/manual/fr/features.commandline.php#78093
*/

function arguments($argv) {
    $_ARG = array();
    foreach ($argv as $arg) {
        if (preg_match('#^-{1,2}([a-zA-Z0-9]*)=?(.*)$#', $arg, $matches)) {
            $key = $matches[1];
            switch ($matches[2]) {
                case '':
                case 'true':
                    $arg = true;
                    break;
                case 'false':
                    $arg = false;
                    break;
                default:
                    $arg = $matches[2];
            }
            $_ARG[$key] = $arg;
        } else {
            $_ARG['input'][] = $arg;
        }
    }
    return $_ARG;
}

function afficher_aide() {
    echo "exemple : php ./montage_zine_ai.php --exemplaires=10" . PHP_EOL;
    echo "--exemplaires=n        : nombre d'exemplaires à fabriquer" . PHP_EOL;
}

function afficher_parametres() {
    global $exemplaires;
    echo "exemplaires           : " . $exemplaires . PHP_EOL;
}

function action($cmd) {
    global $OK;
    echo $cmd . PHP_EOL;
    if ($OK)
        echo exec($cmd) . PHP_EOL;
}

// Traitement des arguments, initialisation des paramètres ********************

$arguments = arguments($argv);

foreach ($arguments as $action => $valeur) {
    if ($action == "aide") {
        afficher_aide();
        exit();
    }
    if ($action == "help") {
        afficher_aide();
        exit();
    }
    if ($action == "exemplaires") {
        $exemplaires = $valeur;
    }
}

afficher_parametres();

// Préparer les pages statiques ***********************************************

foreach ($pages_statiques as $page => $origine) {
    $cmd = "cp ./" . $rep_pages . "/" . $origine . " ./" . $page;
    action($cmd);
}



// ****************************************************************************
// Montage des exemplaires ****************************************************
// ****************************************************************************

$compteur_choix_image = -1; // Utilisé pour boucler dans les répertoires d'images

for ($ex = 0; $ex < $exemplaires; $ex++) {

    $timestamp = date("Ymd_His");
    $compteur_choix_image ++;

    // Préparer les pages dynamiques ******************************************

    foreach ($pages_dynamiques as $page => $rep) {
        $scanrep = array_diff(scandir($rep_pages . "/" . $rep), array('..', '.'));
        // array_diff ne modifie pas les clés du tableau, d'où le +2
        $idx = $compteur_choix_image % (count($scanrep)) + 2;
        $cmd = "cp ./" . $rep_pages . "/" . $rep . "/" . $scanrep[$idx] . " ./" . $page;
        action($cmd);
    }

    // Préparer un chemin de fer **********************************************

    $nom_complet = "./" . $rep_ex . "/" . $nom_zine . "_" . $timestamp . "_chemin_de_fer.png";

    $cmd = "montage ";
    for ($i = 1; $i <= $total_pages; $i++) {
        $cmd .= "page_" . str_pad( $i, 2, '0', STR_PAD_LEFT) . ".png ";
    }
    $cmd .= "-tile 8x4 -geometry 200x280 " . $nom_complet;
    action($cmd);


    // Assembler les pages ****************************************************

    $livret_page = 0;

    for ($i = 0; $i < $total_pages / 4; $i++) {

        $livret_page ++;

        $rog = str_pad(    $total_pages - $i * 2,       2, '0', STR_PAD_LEFT);
        $rod = str_pad(    $i * 2 + 1,                  2, '0', STR_PAD_LEFT);
        $vog = str_pad(    $i * 2 + 2,                  2, '0', STR_PAD_LEFT);
        $vod = str_pad(    $total_pages - $i * 2 - 1,   2, '0', STR_PAD_LEFT);

        $cmd  = "montage -geometry " . $ph . "x" . $pv . " -tile 2x1 ";
        $cmd .= "\( page_" . $rog . ".png -resize " . $ph . "x" . $pv . "^! \) ";
        $cmd .= "\( page_" . $rod . ".png -resize " . $ph . "x" . $pv . "^! \) ";
        $cmd .= "livret_page_" . $livret_page . ".png";

        action($cmd);;

        $livret_page ++;

        $cmd  = "montage -geometry " . $ph . "x" . $pv . " -tile 2x1 ";
        $cmd .= "\( page_" . $vog . ".png -resize " . $ph . "x" . $pv . "^! \) ";
        $cmd .= "\( page_" . $vod . ".png -resize " . $ph . "x" . $pv . "^! \) ";
        $cmd .= "livret_page_" . $livret_page . ".png";

        action($cmd);
    }

    // Créer le pdf imprimable ************************************************

    $nom_complet = "./" . $rep_ex . "/" . $nom_zine . "_" . $timestamp . "_print.pdf";

    $cmd = "convert -limit memory 1GB -limit map 1.5GB ";
    for ($i = 1; $i <= $total_pages / 2; $i++) {
        $cmd .= "livret_page_" . $i . ".png ";
    }
    $cmd .= "-units PixelsPerInch -density " . $densite . " " . $nom_complet;
    action($cmd);

    // Créer le pdf web *******************************************************

    $nom_complet = "./" . $rep_ex . "/" . $nom_zine . "_" . $timestamp . "_web.pdf";

    $cmd = "convert -limit memory 1GB -limit map 1.5GB ";
    for ($i = 1; $i <= $total_pages; $i++) {
        $cmd .= "page_" . str_pad( $i, 2, '0', STR_PAD_LEFT) . ".png ";
    }
    $cmd .= "-units PixelsPerInch -density " . $densite . " ";
    $cmd .= "-compress jpeg -quality 90% " . $nom_complet;
    action($cmd);

    // Effacer les pages montées temporaires **********************************

    for ($i = 1; $i <= $total_pages / 2; $i++) {
        $cmd = "rm ./livret_page_" . $i . ".png ";
        action($cmd);
    }
}

// Effacer les pages temporaires **********************************************

for ($i = 1; $i <= $total_pages; $i++) {
    $cmd = "rm ./page_" . str_pad( $i, 2, '0', STR_PAD_LEFT) . ".png ";
    action($cmd);
}

?>
