#!/bin/bash
# https://mywiki.wooledge.org/BashFAQ/105
# https://gist.github.com/mohanpedala/1e2ff5661761d3abd0385e8223e16425
set -euxo pipefail

chmod 600 .pgpass
export PGPASSFILE=.pgpass

filter_dump() {
    # https://stackoverflow.com/questions/11454343/pipe-output-to-bash-function/11455201#11455201
    cat |
        # https://stackoverflow.com/questions/41941527/removing-comments-from-pg-dump-output/41972898#41972898
        # https://stackoverflow.com/questions/16414410/delete-empty-lines-using-sed
        # https://dba.stackexchange.com/questions/315063/disable-toast-compression-for-all-columns
        sed -E '/(^(SET |--)|^$|SET STORAGE EXTERNAL;$)/d' |
        sed -E 's/^(ALTER TABLE) ONLY/\1/g' |
        # https://unix.stackexchange.com/questions/26284/how-can-i-use-sed-to-replace-a-multi-line-string/26289#26289
        perl -p0e 's/\n    (ADD CONSTRAINT)/ \1/g' |
        sed -e "/^SELECT pg_catalog.set_config('search_path', '', false);$/d"
}
dump_table() {
    local table=${2:?}
    pg_dump -U$username -sOxn \"$schema\" -t \"$table\" |
        filter_dump
    echo # https://unix.stackexchange.com/questions/690635/how-can-i-add-a-new-line-after-the-output-of-a-command
}
dump_db_and_schema() {
    # https://dba.stackexchange.com/questions/258183/pg-dump-table-dependencies-when-using-table/347112#347112
    pg_dump -U$username -sOx -T '*' |
        filter_dump
    echo # https://unix.stackexchange.com/questions/690635/how-can-i-add-a-new-line-after-the-output-of-a-command
}

# https://stackoverflow.com/questions/3601515/how-to-check-if-a-variable-is-set-in-bash/16753536#16753536
username=${1:?}
schema=${2:?}
dump_db_and_schema
# https://dba.stackexchange.com/questions/292696/can-i-make-postgres-pg-dump-condense-the-alter-table-statements-in-the-create-t/292703#292703
psql -U$username -XAtc "SELECT tablename FROM pg_tables WHERE schemaname = '$schema' ORDER BY tablename;" \
    | mapfile -tc1 -C dump_table
