<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928154747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alert (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, acknowledged_at DATETIME DEFAULT NULL, severity VARCHAR(10) NOT NULL, message VARCHAR(255) NOT NULL, value DOUBLE PRECISION DEFAULT NULL, acknowledged_by_id INT DEFAULT NULL, device_id INT NOT NULL, rule_id INT DEFAULT NULL, INDEX idx_alert_open (acknowledged_at, created_at), INDEX IDX_17FD46C14DEFF5F (acknowledged_by_id), INDEX IDX_17FD46C194A4C7D4 (device_id), INDEX IDX_17FD46C1744E0351 (rule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE alert_rule (id INT AUTO_INCREMENT NOT NULL, last_triggered_at DATETIME DEFAULT NULL, metric VARCHAR(20) NOT NULL, operator VARCHAR(2) NOT NULL, threshold DOUBLE PRECISION NOT NULL, severity VARCHAR(10) NOT NULL, cooldown_minutes INT NOT NULL, enabled TINYINT NOT NULL, device_id INT NOT NULL, INDEX IDX_C9687E4894A4C7D4 (device_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE device (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, type VARCHAR(20) NOT NULL, hardware VARCHAR(40) NOT NULL, mac_address VARCHAR(17) NOT NULL, firmware_version VARCHAR(20) DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, token_prefix VARCHAR(12) NOT NULL, armed TINYINT NOT NULL, last_seen_at DATETIME DEFAULT NULL, last_ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, site_id INT NOT NULL, UNIQUE INDEX UNIQ_92FB68EB728E969 (mac_address), UNIQUE INDEX UNIQ_92FB68EB3BC57DA (token_hash), INDEX IDX_92FB68EF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, location VARCHAR(120) DEFAULT NULL, timezone VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_694309E47E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(80) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C14DEFF5F FOREIGN KEY (acknowledged_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C194A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C1744E0351 FOREIGN KEY (rule_id) REFERENCES alert_rule (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE alert_rule ADD CONSTRAINT FK_C9687E4894A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68EF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_694309E47E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alert DROP FOREIGN KEY FK_17FD46C14DEFF5F');
        $this->addSql('ALTER TABLE alert DROP FOREIGN KEY FK_17FD46C194A4C7D4');
        $this->addSql('ALTER TABLE alert DROP FOREIGN KEY FK_17FD46C1744E0351');
        $this->addSql('ALTER TABLE alert_rule DROP FOREIGN KEY FK_C9687E4894A4C7D4');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68EF6BD1646');
        $this->addSql('ALTER TABLE site DROP FOREIGN KEY FK_694309E47E3C61F9');
        $this->addSql('DROP TABLE alert');
        $this->addSql('DROP TABLE alert_rule');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE `user`');
    }
}
