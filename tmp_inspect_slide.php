<?php

$z = new ZipArchive;
$z->open('resources/plantillas/Clase_Clase.pptx');
$xml = $z->getFromName('ppt/slides/slide2.xml');
if (preg_match("/<p:sp>.*?\{CONTENIDO\}.*?<\/p:sp>/s", $xml, $m)) {
    echo $m[0];
}
echo "\n---- HORARIOS ASIG 14 ----\n";
