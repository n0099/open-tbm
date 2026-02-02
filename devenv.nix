{
  config,
  pkgs,
  lib,
  ...
}:

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
      fpm.pools.www.settings =
        let
          nproc = pkgs.runCommandLocal "nproc" { } "nproc > $out" |> lib.readFile |> lib.toIntBase10;
        in
        {
          pm = "dynamic";
          "pm.max_children" = nproc * 2;
          "pm.min_spare_servers" = 1;
          "pm.max_spare_servers" = nproc;
        };
    };
    enterShell = /* sh */ "(cd be && composer i)";
    services.nginx = {
      enable = true;
      httpConfig = ''
        server {
          listen 8080;
          server_name localhost;
          index index.php index.html;

          # https://github.com/nginxinc/nginx-wiki/blob/836ecd605a1b9861fb608e848336bca9b8640b54/source/start/topics/examples/phpfcgi.rst
          location ~ [^/]\.php(/|$) {
              fastcgi_index index.php;
              fastcgi_split_path_info ^(.+?\.php)(/.*)$;
              fastcgi_pass unix:${config.env.PHPFPMDIR}/www.sock; # https://github.com/cachix/devenv/blob/a208bf67ac2874fec086aa94cfdead0f40de3613/src/modules/languages/php.nix#L155
              add_header X-Powered-By php always;

              # https://serverfault.com/questions/627903/is-the-php-option-cgi-fix-pathinfo-really-dangerous-with-nginx-php-fpm
              if (!-f $request_filename) {
                  return 404;
              }
              try_files $uri =404;

              # https://httpoxy.org
              fastcgi_param HTTP_PROXY "";

              include ${pkgs.nginx}/conf/fastcgi_params;
              # https://github.com/nginxinc/nginx-wiki/blob/836ecd605a1b9861fb608e848336bca9b8640b54/source/start/topics/tutorials/config_pitfalls.rst#use-request_filename-for-script_filename
              fastcgi_param SCRIPT_FILENAME $request_filename;
              # https://serverfault.com/questions/465607/nginx-document-rootfastcgi-script-name-vs-request-filename
              # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
          }
        }
      '';
    };
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
      if [ $DEVENV_CMDLINE != up ] && command -v zsh > /dev/null
      then
        exec zsh
      fi
    '';
  }
]
