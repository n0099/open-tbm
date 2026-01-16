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
          http_proxy=''${http_proxy/socks5h/socks5} # `error NU1301:   Only the 'http', 'https', 'socks4', 'socks4a' and 'socks5' schemes are allowed for proxies.` while `dotnet restore`
          (cd c# && dotnet restore)
        '';
      };
      be = pkgs.mkShellNoCC {
        packages = with pkgs; [
          php84
          php84Packages.composer
        ];
        shellHook = "(cd be && composer i)";
      };
      fe = pkgs.mkShellNoCC {
        packages = with pkgs; [
          nodejs_24
          yarn-berry_4
        ];
        shellHook = ''
          ( cd fe && yarn &&
            ( [ ! -f nuxt-dev.key ] &&
              [ ! -f nuxt-dev.crt ] &&
              ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=localhost &> /dev/null
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
          init # https://github.com/NixOS/nixpkgs/blob/0726f235730331846135184e71d1d1bc3a4b49ad/pkgs/build-support/mkshell/default.nix#L54
          cs
          be
          fe
        ];
        shellHook = ''
          git submodule init
          git submodule update
          command -v zsh > /dev/null && exec zsh
        '';
      };
    };
}
