-- Base dédiée aux tests fonctionnels (suffixe _test ajouté par DoctrineBundle)
CREATE DATABASE IF NOT EXISTS sentinelhub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `sentinelhub%`.* TO 'sentinelhub'@'%';
FLUSH PRIVILEGES;
