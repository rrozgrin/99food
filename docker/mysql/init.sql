CREATE DATABASE IF NOT EXISTS base_erp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS marketplace
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON base_erp.* TO 'food99'@'%';
GRANT ALL PRIVILEGES ON marketplace.* TO 'food99'@'%';

FLUSH PRIVILEGES;