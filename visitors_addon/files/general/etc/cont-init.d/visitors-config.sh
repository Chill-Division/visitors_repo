#!/usr/bin/with-contenv bashio

# Create config directory if it doesn't exist
mkdir -p /visitors_config

# Get admin password from addon config with default value
ADMIN_PASSWORD=$(bashio::config 'admin_password' 'SetSomethingStrongHere')

# Write config to JSON file
bashio::log.info "Writing visitors configuration..."
{
    echo "{"
    echo "  \"admin_password\": \"${ADMIN_PASSWORD}\""
    echo "}"
} > /visitors_config/options.json

# Set proper permissions
chmod 644 /visitors_config/options.json
