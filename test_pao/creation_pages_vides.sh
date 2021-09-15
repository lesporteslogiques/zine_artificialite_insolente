#!/bin/bash
# créer des pages vides pour test montage

for i in $(seq -f "%02g" 1 32)
do
    (convert -background white -fill black -size 877x1240 -gravity center label:"$i" -shave 1x1 -bordercolor black -border 1 page_"$i".png)
done
