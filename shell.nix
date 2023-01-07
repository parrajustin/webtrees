{ pkgs ? import <nixpkgs> {} }:
  pkgs.mkShell {
    # nativeBuildInputs is usually what you want -- tools you need to run
    nativeBuildInputs = [ 
      pkgs.php
      pkgs.php81Packages.composer
      pkgs.nodejs-16_x
      pkgs.yarn
      ];

  LOCALE_ARCHIVE="/usr/lib/locale/locale-archive";
}
