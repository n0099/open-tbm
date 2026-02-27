{
  config,
  pkgs,
  lib,
  ...
}:

let
  cfg = {
    domain = "localhost";
    port = {
      fe = 3000;
      be = 8080;
    };
    baseURL = {
      fe = "/tbm";
      be = "/tbm/be";
    };
    pgsql = {
      version = 17;
      db = "tbm";
      user = "tbm";
    };
    dotnet.version = 9;
  };
  mkCWD = path: { cwd = "${config.git.root}/${path}"; };
  mkTaskBeforeEnterShell =
    path:
    mkCWD path
    // {
      before = [ "devenv:enterShell" ];
    };
  mkDotenv = path: text: {
    exec = /* sh */ ''
      cat <<'EOF' > ./${path}
      ${text}
      EOF
    '';
    status = /* sh */ ''
      [ -f ${path} ]
    '';
  };
  cs = {
    languages.dotnet = {
      enable = true;
      package = pkgs."dotnet-sdk_${toString cfg.dotnet.version}";
    };
    tasks = {
      "deps:install:cs" = mkTaskBeforeEnterShell "c#" // {
        exec = /* sh */ ''
          http_proxy=''${http_proxy/socks5h/socks5} # `error NU1301:   Only the 'http', 'https', 'socks4', 'socks4a' and 'socks5' schemes are allowed for proxies.` while `dotnet restore`
          dotnet restore
        '';
        execIfModified = [ "*/packages.lock.json" ];
      };
      "dotenv:cs" =
        mkDotenv "c#/crawler/bin/Debug/net${toString cfg.dotnet.version}.0/appsettings.Development.json"
          (
            builtins.toJSON {
              ConnectionStrings.Main = "Host=${config.env.PGHOST};Username=${cfg.pgsql.user};Database=${cfg.pgsql.db}";
              ClientRequester.LogTrace = true;
              ClientRequesterTcs.LogTrace.Enabled = true;
              CrawlerLocks = lib.genAttrs [ "Thread" "ThreadLate" "Reply" "SubReply" ] (_: {
                LogTrace.Enable = true;
              });
            }
          );
    };
  };
  pgsql = {
    services.postgres = {
      enable = true;
      package = pkgs."postgresql_${toString cfg.pgsql.version}";
      initialDatabases = [
        {
          name = cfg.pgsql.db;
          inherit (cfg.pgsql) user;
          pass = ""; # there's should be a line `local all all trust` in https://www.postgresql.org/docs/current/auth-pg-hba-conf.html to skip auth for any connection via UNIX domain socket
          schema = ./sql/schema.sql;
          initialSQL = /* sql */ ''
            -- https://stackoverflow.com/questions/67578997/postgres-show-altered-role-config-paramenters
            ALTER ROLE ALL IN DATABASE ${cfg.pgsql.db} SET search_path TO 'tbm';
          '';
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
    tasks = {
      "deps:install:be" = mkTaskBeforeEnterShell "be" // {
        exec = /* sh */ "composer install --no-interaction";
        execIfModified = [ "composer.lock" ];
      };
      "dotenv:be" = mkDotenv "be/.env.local" /* sh */ ''
        APP_ENV=dev
        APP_BASE_URL_BE=http://${cfg.domain}${cfg.baseURL.be}
        APP_BASE_URL_FE=https://${cfg.domain}${cfg.baseURL.fe}

        # https://github.com/cachix/devenv/blob/59bdb8c9c9d7fdcfbd15a7c4cfb14fb6c6d5d6ce/src/modules/services/postgres.nix#L408-L416
        # https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNSTRING-URIS
        DATABASE_URL="postgresql://${cfg.pgsql.db}/?dbname=${cfg.pgsql.db}&host=${config.env.PGHOST}&user=${cfg.pgsql.user}&serverVersion=${toString cfg.pgsql.version}&charset=utf8"
      '';
    };
    enterShell = /* sh */ ''
      export PATH=${config.devenv.root}/be/vendor/bin:"$PATH" # https://github.com/cachix/devenv/blob/d4ffee46c9088df6e000470b998a2d2c16517f62/src/modules/languages/javascript.nix#L352
    '';
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
          listen ${toString cfg.port.be};
          server_name ${cfg.domain};
          index index.php index.html;

          # https://github.com/n0099/siye-srv-ops/blob/97309d3d3ddb79d198b5fd0e52055106b80942cd/base/s6.nginx.php-fpm/nginx/templates/sub-base-dir.conf
          # https://github.com/n0099/siye-srv-ops/blob/97309d3d3ddb79d198b5fd0e52055106b80942cd/srv/tbm/v2/.env#L4
          location ${cfg.baseURL.be}/ {
            # https://serverfault.com/questions/674604/nginx-how-to-strip-location-prefix-in-fastcgi-script-name/690009#690009
            alias ${config.git.root}/be/public/;

            # https://serverfault.com/questions/455799/how-to-remove-location-block-from-uri-in-nginx-configuration/1172730#1172730
            # https://stackoverflow.com/questions/20426812/nginx-try-files-alias-directives/35102259#35102259
            try_files $uri $uri/ ${cfg.baseURL.be}/${cfg.baseURL.be}/index.php?$query_string;

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

              fastcgi_param HTTP_PROXY ""; # https://httpoxy.org
              include ${pkgs.nginx}/conf/fastcgi_params;

              # https://github.com/nginxinc/nginx-wiki/blob/836ecd605a1b9861fb608e848336bca9b8640b54/source/start/topics/tutorials/config_pitfalls.rst#use-request_filename-for-script_filename
              fastcgi_param SCRIPT_FILENAME $request_filename;
              # https://serverfault.com/questions/465607/nginx-document-rootfastcgi-script-name-vs-request-filename
              # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

              # https://github.com/monsur/enable-cors.org/commit/ad7acaa164bd551d716b9c1ce9dcf72515236481
              add_header 'Access-Control-Allow-Origin' 'https://${cfg.domain}:${toString cfg.port.fe}' always;
              add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
              add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization' always;
              add_header 'Vary' 'Origin' always;
              if ($request_method = 'OPTIONS') {
                add_header 'Access-Control-Max-Age' 86400;
                return 204;
              }
            }
          }
        }
      '';
    };
    tasks."devenv:processes:nginx".after = [
      "deps:install:be"
      "dotenv:be"
    ];
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
        directory = "${config.devenv.root}/fe";
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
          ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=${cfg.domain} &> /dev/null
        '';
        status = /* sh */ ''
          [ -f nuxt-dev.key ] && [ -f nuxt-dev.crt ]
        '';
      };
      "dotenv:fe" = mkDotenv "fe/.env" /* sh */ ''
        NUXT_APP_BASE_URL=${cfg.baseURL.fe}
        NUXT_SITE_URL=https://${cfg.domain}:${toString cfg.port.fe} # https://github.com/harlan-zw/nuxt-site-config/issues/32
        NUXT_PUBLIC_BE_URL=http://${cfg.domain}:${toString cfg.port.be}${cfg.baseURL.be}
        NUXT_PUBLIC_INSTANCE_NAME=dev
        NUXT_PUBLIC_FOOTER_TEXT=dev
        NUXT_PUBLIC_TIEBA_IMAGE_PROXY=https://n0099.com/tbm/imgsrc
      '';
    };
  };
  fe_processes = {
    processes.fe = mkCWD "fe" // {
      exec = "yarn dev";
    };
    tasks."devenv:processes:fe".after = [
      "deps:install:fe"
      "fe:gen-nuxt-cert"
      "dotenv:fe"
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
  pgsql
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
