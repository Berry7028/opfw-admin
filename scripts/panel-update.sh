cd opfw-admin

echo "pulling" > update

git stash
git pull

export COMPOSER_ALLOW_SUPERUSER=1

composer install

for directory in ./envs/c*/; do
    [ -L "${d%/}" ] && continue

    cluster="$(basename -- $directory)"

    echo "migrating $cluster" > update

    echo "Migrating $cluster";
    php artisan migrate --cluster=$cluster --force

    #echo "Rolling back $cluster";
    #php artisan migrate:rollback --cluster=$cluster --step=1 --force
done

rm update
