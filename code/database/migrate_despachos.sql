-- Migración para bases ya importadas: persistir despachos sin cliente catalogado.
USE `distribuidora_agua`;

ALTER TABLE `despachos` DROP FOREIGN KEY `fk_despachos_cliente`;
ALTER TABLE `despachos` MODIFY `cliente_id` INT NULL;
ALTER TABLE `despachos`
    ADD CONSTRAINT `fk_despachos_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `despachos`
    ADD COLUMN IF NOT EXISTS `nombre_cliente_raw` VARCHAR(150) NULL AFTER `cliente_id`,
    ADD COLUMN IF NOT EXISTS `alias_despacho_consolidado` VARCHAR(150) NULL AFTER `nombre_cliente_raw`;

ALTER TABLE `despachos`
    ADD INDEX `idx_despachos_fecha_despachador` (`fecha`, `despachador`);
