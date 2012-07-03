--
-- Table structure for table `log_actions`
--

CREATE TABLE `log_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(36) NOT NULL,
  `row` int(11) NOT NULL,
  `model` varchar(50) NOT NULL,
  `field` varchar(50) NOT NULL,
  `before` text NOT NULL,
  `after` text NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
