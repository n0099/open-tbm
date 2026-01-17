{
  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
  outputs =
    { nixpkgs, ... }:

    let
      system = "x86_64-linux";
      pkgs = import nixpkgs { inherit system; };
      lib = nixpkgs.lib;
      init = pkgs.mkShellNoCC {
        shellHook = ''
          # https://gist.github.com/mohanpedala/1e2ff5661761d3abd0385e8223e16425
          # https://mywiki.wooledge.org/BashPitfalls#set_-euo_pipefail
          set -euxo pipefail
        '';
      };
      cs = pkgs.mkShellNoCC {
        packages = [ pkgs.dotnet-sdk_9 ];
        shellHook = ''
          ( http_proxy=''${http_proxy/socks5h/socks5} && # `error NU1301:   Only the 'http', 'https', 'socks4', 'socks4a' and 'socks5' schemes are allowed for proxies.` while `dotnet restore`
            cd c# &&
            dotnet restore
          )
        '';
      };
      be =
        let
          php = pkgs.php84.buildEnv {
            # https://wiki.nixos.org/wiki/PHP#Use_php_Packages_with_Extensions_in_a_nix-shell
            extensions = { enabled, all }: enabled ++ [ all.xdebug ];
            extraConfig = "xdebug.mode=debug";
          };
        in
        pkgs.mkShellNoCC {
          packages = [
            php
            php.packages.composer
          ];
          shellHook = "(cd be && composer i)";
        };
      fe = pkgs.mkShellNoCC {
        packages = with pkgs; [
          nodejs_24
          corepack_24
        ];
        shellHook = ''
          ( cd fe &&
            unset https_proxy && # https://github.com/nodejs/corepack/issues/703
            yarn &&
            ( if [ ! -f nuxt-dev.key ] && [ ! -f nuxt-dev.crt ]
              then
                ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=localhost &> /dev/null
              fi
            )
          )
        '';
      };
    in
    {
      devShells."${system}".default = pkgs.mkShellNoCC {
        inputsFrom = [
          # https://gist.github.com/adisbladis/2a44cded73e048458a815b5822eea195
          # https://discourse.nixos.org/t/what-does-mkshells-mergeinputs-actually-do/56156
          cs
          be
          fe
          init # https://github.com/NixOS/nixpkgs/blob/0726f235730331846135184e71d1d1bc3a4b49ad/pkgs/build-support/mkshell/default.nix#L54
        ];
        shellHook = ''
          git submodule init
          git submodule update
          command -v zsh > /dev/null && exec zsh
        '';
      };
    };
}
