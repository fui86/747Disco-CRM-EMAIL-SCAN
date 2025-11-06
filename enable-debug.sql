-- ========================================
-- ABILITA DEBUG COMPLETO - 747 Disco CRM
-- ========================================
-- Esegui questo SQL nel database WordPress
-- phpMyAdmin o via SSH: mysql -u username -p database_name < enable-debug.sql
-- ========================================

-- Abilita debug mode del plugin
INSERT INTO wp_options (option_name, option_value, autoload) 
VALUES ('disco747_debug_mode', '1', 'yes')
ON DUPLICATE KEY UPDATE option_value = '1';

-- Verifica che sia stato impostato
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name = 'disco747_debug_mode';
