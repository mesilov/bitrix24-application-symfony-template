#!/bin/bash
set -e

# Substitute environment variables in definitions.json template using sed
sed -e "s/\${RABBITMQ_ADMIN_USER}/$RABBITMQ_ADMIN_USER/g" \
    -e "s/\${RABBITMQ_ADMIN_PASSWORD}/$RABBITMQ_ADMIN_PASSWORD/g" \
    -e "s/\${RABBITMQ_APP_USER}/$RABBITMQ_APP_USER/g" \
    -e "s/\${RABBITMQ_APP_PASSWORD}/$RABBITMQ_APP_PASSWORD/g" \
    /etc/rabbitmq/definitions.json > /tmp/definitions.json
mv /tmp/definitions.json /etc/rabbitmq/definitions.json

# Call the original entrypoint
exec docker-entrypoint.sh "$@"