{
  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
  outputs =
    { nixpkgs, ... }:

    let
      system = "x86_64-linux";
      pkgs = import nixpkgs { inherit system; };
      lib = nixpkgs.lib;
      cs = pkgs.mkShellNoCC { packages = [ pkgs.dotnet-sdk_9 ]; };
      be = pkgs.mkShellNoCC {
        packages = with pkgs; [
          php84
          php84Packages.composer
        ];
      };
      fe = pkgs.mkShellNoCC {
        packages = with pkgs; [
          nodejs_24
          yarn-berry_4
        ];
        shellHook = ''
          ( cd fe &&
            [ ! -f nuxt-dev.key ] &&
            [ ! -f nuxt-dev.crt ] &&
            ${lib.getExe pkgs.openssl} req -x509 -newkey rsa:8192 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj /CN=localhost &> /dev/null
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
        ];
        shellHook = "command -v zsh > /dev/null && exec zsh";
      };
    };
}
