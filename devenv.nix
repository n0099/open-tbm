{
  config,
  pkgs,
  lib,
  ...
}:

let
  mkTaskBeforeEnterShell = path: {
    cwd = "${config.git.root}/${path}";
    before = [ "devenv:enterShell" ];
  };
  cs = {
    languages.dotnet = {
      enable = true;
      package = pkgs.dotnet-sdk_9;
    };
    tasks."deps:install:cs" = mkTaskBeforeEnterShell "c#" // {
      exec = /* sh */ ''
        http_proxy=''${http_proxy/socks5h/socks5} # `error NU1301:   Only the 'http', 'https', 'socks4', 'socks4a' and 'socks5' schemes are allowed for proxies.` while `dotnet restore`
        dotnet restore
      '';
      execIfModified = [ "*/packages.lock.json" ];
    };
  };
  cs_processes = {
    services.postgres = {
      enable = true;
      package = pkgs.postgresql_17;
      initialDatabases = [
        {
          name = "tbm";
          schema = ./sql/schema.sql;
        }
      ];
    };
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
    tasks."deps:install:be" = mkTaskBeforeEnterShell "be" // {
      exec = /* sh */ "composer install --no-interaction";
      execIfModified = [ "composer.lock" ];
    };
  };
  be_processes = {
    languages.php.fpm.pools.www.settings =
      let
        nproc = pkgs.runCommandLocal "nproc" { } "nproc > $out" |> lib.readFile |> lib.toIntBase10;
      in
      {
        pm = "dynamic";
        "pm.max_children" = nproc * 2;
        "pm.min_spare_servers" = 1;
        "pm.max_spare_servers" = nproc;
      };
    services.nginx = {
      enable = true;
      httpConfig = ''
        server {
          listen 8080;
          server_name localhost;
          index index.php index.html;

          # https://github.com/n0099/siye-srv-ops/blob/97309d3d3ddb79d198b5fd0e52055106b80942cd/base/s6.nginx.php-fpm/nginx/templates/sub-base-dir.conf
          # https://github.com/n0099/siye-srv-ops/blob/97309d3d3ddb79d198b5fd0e52055106b80942cd/srv/tbm/v2/.env#L4
          location /tbm/be/ {
            # https://serverfault.com/questions/674604/nginx-how-to-strip-location-prefix-in-fastcgi-script-name/690009#690009
            alias ${config.git.root}/be/public/;

            # https://serverfault.com/questions/455799/how-to-remove-location-block-from-uri-in-nginx-configuration/1172730#1172730
            # https://stackoverflow.com/questions/20426812/nginx-try-files-alias-directives/35102259#35102259
            try_files $uri $uri/ /tbm/be/tbm/be/index.php?$query_string;

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
        }
      '';
    };
  };
  fe = {
    languages = {
      javascript = {
        enable = true;
        package = pkgs.nodejs-slim_24;
        yarn = {
          enable = true;
          package = pkgs.yarn-berry_4;
        };
        directory = "fe";
      };
      typescript.enable = true;
    };
    tasks = {
      "deps:install:fe" = mkTaskBeforeEnterShell "fe" // {
        exec = "yarn";
        execIfModified = [ "yarn.lock" ];
      };
      "fe:gen-nuxt-cert" = mkTaskBeforeEnterShell "fe" // {
        exec = /* sh */ ''
          ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=localhost &> /dev/null
        '';
        status = /* sh */ ''
          [ -f nuxt-dev.key ] && [ -f nuxt-dev.crt ]
        '';
      };
    };
  };
  fe_processes = {
    processes.fe = {
      exec = "yarn dev";
      cwd = "${config.git.root}/fe";
    };
    tasks."devenv:processes:fe".after = [
      "deps:install:fe"
      "fe:gen-nuxt-cert"
    ];
  };
  git_submodule.tasks."git:submodule:init" = mkTaskBeforeEnterShell "tbclient.protobuf" // {
    exec = /* sh */ ''
      git submodule init
      git submodule update
    '';
    status = /* sh */ ''
      # https://superuser.com/questions/352289/bash-scripting-test-for-empty-directory#comment2000147_352387
      [ ! "$(find -maxdepth 0 -type d -empty | grep -F .)" ]
    '';
  };
in
lib.mkMerge [
  cs
  cs_processes
  be
  be_processes
  fe
  fe_processes
  git_submodule
  {
    packages = with pkgs; [ git ];
    enterShell = /* sh */ ''
      if [ $DEVENV_CMDLINE != up ] && command -v zsh > /dev/null
      then
        exec zsh
      fi
    '';
  }
]
