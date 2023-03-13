<?php

// Copyright (C) 2020      MB Informatique        <info@mb-informatique.fr>

function drawBorder(&$img, &$color, $thickness = 1)
{
    $x1 = 0;
    $y1 = 0;
    $x2 = ImageSX($img) - 1;
    $y2 = ImageSY($img) - 1;

    for($i = 0; $i < $thickness; $i++)
    {
        ImageRectangle($img, $x1++, $y1++, $x2--, $y2--, $color);
    }
}

$image = imagecreate(186,50);
$background_color = imagecolorallocate($image, 255, 255, 255);
$color = imagecolorallocate($image, 0, 0, 0);
$phone = $facture->thirdparty->phone;

imagestring($image, 4, 35, 15, $phone, $color);

drawBorder($image,$color, 4);

$urlpng = DOL_DOCUMENT_ROOT. $path . "/gd/png/".$hashp.".png";

imagepng($image, $urlpng);
$image = imagecreatefrompng($urlpng);
