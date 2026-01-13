#!/usr/bin/env bash
set -eu
declare -a directories=(
  "web/libraries/chart.js/auto"
  "web/libraries/chart.js/helpers"
  "web/libraries/chart.js/types"
  "web/libraries/chart.js/dist/chunks"
  "web/libraries/chart.js/dist/docs"
  "web/libraries/chart.js/dist/controllers"
  "web/libraries/chart.js/dist/core"
  "web/libraries/chart.js/dist/elements"
  "web/libraries/chart.js/dist/helpers"
  "web/libraries/chart.js/dist/platform"
  "web/libraries/chart.js/dist/plugins"
  "web/libraries/chart.js/dist/scales"
  "web/libraries/chart.js/dist/types"
  "web/libraries/chartjs-plugin-datalabels/types"
)
counter=0
echo "Deleting unneeded directories inside web/libraries/chartjs"
for directory in "${directories[@]}"
  do
    if [ -d $directory ]; then
      echo "Deleting $directory"
      rm -rf $directory
      counter=$((counter+1))
    fi
  done
echo "$counter folders were deleted"
declare -a files=(
  "web/libraries/chart.js/README.md"
  "web/libraries/chart.js/LICENSE.md"
  "web/libraries/chart.js/package.json"
  "web/libraries/chart.js/dist/helpers.esm.js"
  "web/libraries/chart.js/dist/helpers.mjs"
  "web/libraries/chart.js/dist/chart.mjs"
  "web/libraries/chart.js/dist/chart.esm.js"
  "web/libraries/chart.js/dist/chart.cjs"
  "web/libraries/chart.js/dist/chart.cjs.map"
  "web/libraries/chart.js/dist/helpers.js"
  "web/libraries/chart.js/dist/helpers.js.map"
  "web/libraries/chart.js/dist/helpers.cjs"
  "web/libraries/chart.js/dist/helpers.cjs.map"
  "web/libraries/chart.js/dist/index.d.ts"
  "web/libraries/chart.js/dist/index.umd.d.ts"
  "web/libraries/chart.js/dist/types.d.ts"
  "web/libraries/chartjs-adapter-date-fns/README.md"
  "web/libraries/chartjs-adapter-date-fns/LICENSE.md"
  "web/libraries/chartjs-adapter-date-fns/package.json"
  "web/libraries/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.esm.js"
  "web/libraries/chartjs-plugin-datalabels/README.md"
  "web/libraries/chartjs-plugin-datalabels/LICENSE.md"
  "web/libraries/chartjs-plugin-datalabels/package.json"
  "web/libraries/chartjs-plugin-datalabels/bower.json"
)
counter=0
echo "Deleting unneeded files inside web/libraries/chartjs"
for file in "${files[@]}"
  do
    if [[ -f $file ]]; then
      echo "Deleting $file"
      rm $file
      counter=$((counter+1))
    fi
  done
echo "$counter files were deleted"
