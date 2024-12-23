#!/bin/bash

command=$1

common_arguments="--style expanded --no-source-map --load-path=private/skins"

common_arguments+="--silence-deprecation=import,global-builtin"

if [ "$command" = "dev" ]; then
  common_arguments+=" --watch --poll"
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

# scan for skins
scss_dirs=""
for skin_dir in private/skins/*; do
  if [ -d "$skin_dir/scss" ]; then
    scss_dirs+=" ${skin_dir}/scss:public/assets/css"
  fi
done

# Check if there are any SCSS directories to compile
if [ -z "$scss_dirs" ]; then
  echo "You do not have any skins."
  exit 1
fi

# Compile SCSS directories
${sass_executable} ${common_arguments} ${scss_dirs}

echo "SCSS compilation complete."
