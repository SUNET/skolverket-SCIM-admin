CREATE TABLE `params` (
  `instance` varchar(20) DEFAULT NULL,
  `id` varchar(20) DEFAULT NULL,
  `value` text DEFAULT NULL
)

INSERT INTO `params` (`instance`, `id`, `value`) VALUES ('', 'dbVersion', '1');
