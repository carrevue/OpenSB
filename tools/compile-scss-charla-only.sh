#!/bin/bash

command=$1

common_arguments="--verbose --style expanded --no-source-map --load-path=private/skins"

if [ "$command" != "deprecated" ]; then
  common_arguments+="--silence-deprecation=import,global-builtin"
fi

if [ "$command" = "dev" ]; then
  common_arguments+=" --watch --poll"
fi

if [ "$command" = "deprecated" ]; then
  common_arguments+=" --fatal-deprecation=mixed-decls,color-functions"
fi

machine=$(uname -s)
case "${machine}" in
  CYGWIN*|MINGW*|MSYS*) machine=Windows;;
  *)                    machine=Other;;
esac

if [ "$machine" == "Windows" ]; then
  if ! sass --version &> /dev/null; then
    sass_executable="sass.bat"
  else
    sass_executable="sass"
  fi
else
  if ! sass --version &> /dev/null; then
    if command -v dart-sass &> /dev/null; then
      sass_executable="dart-sass"
    else
      echo "Please install Dart-Sass."
      exit 1
    fi
  else
    sass_executable="sass"
  fi
fi

if [ ! -d "public/assets/css" ]; then
  mkdir -p public/assets/css || { echo "Error: Failed to create public/assets/css directory"; exit 1; }
fi

# Compile SCSS directories
${sass_executable} ${common_arguments} private/skins/charla/scss/:public/assets/css/ private/skins/error/scss/:public/assets/css/

echo "SCSS compilation complete."
