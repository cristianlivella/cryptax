SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `cache` (
  `id` bigint(20) NOT NULL,
  `ticker` varchar(20) NOT NULL,
  `name` varchar(300) NOT NULL,
  `date` date NOT NULL,
  `quote` double NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `crypto_id` (`ticker`,`date`);

ALTER TABLE `cache`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;
