{ pkgs, lib, ... }:

let
  cs = {
    languages.dotnet = {
      enable = true;
      package = pkgs.dotnet-sdk_9;
    };
    enterShell = /* sh */ ''
      (
        http_proxy=''${http_proxy/socks5h/socks5} && # `error NU1301:   Only the 'http', 'https', 'socks4', 'socks4a' and 'socks5' schemes are allowed for proxies.` while `dotnet restore`
        cd c# &&
        dotnet restore
      )
    '';
  };
  be = {
    languages.php = {
      enable = true;
      package = pkgs.php84.buildEnv {
        # https://wiki.nixos.org/wiki/PHP#Use_php_Packages_with_Extensions_in_a_nix-shell
        extensions = { enabled, all }: enabled ++ [ all.xdebug ];
        extraConfig = "xdebug.mode=debug";
      };
    };
    enterShell = /* sh */ "(cd be && composer i)";
  };
  fe = {
    languages = {
      javascript = lib.mkMerge [
        {
          enable = true;
          package = pkgs.nodejs-slim_24;
          yarn = {
            enable = true;
            package = pkgs.yarn-berry_4;
          };
        }
        {
          directory = "fe";
          yarn.install.enable = true;
        }
      ];
      typescript.enable = true;
    };
    enterShell = /* sh */ ''
      ( cd fe &&
        if [ ! -f nuxt-dev.key ] && [ ! -f nuxt-dev.crt ]
        then
          ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=localhost &> /dev/null
        fi
      )
    '';
  };
in
lib.mkMerge [
  cs
  be
  fe
  {
    packages = with pkgs; [ git ];
    enterShell = /* sh */ ''
      git submodule init
      git submodule update
      command -v zsh > /dev/null && exec zsh
    '';
  }
]
