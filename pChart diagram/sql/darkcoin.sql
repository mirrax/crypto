CREATE TABLE IF NOT EXISTS `address` (
  `id` int(11) NOT NULL,
  `address` varchar(128) NOT NULL,
  `label` varchar(128) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `data` (
  `id` int(11) NOT NULL,
  `bid` varchar(128) NOT NULL,
  `diff` int(11) NOT NULL,
  `address` varchar(128) NOT NULL,
  `time` varchar(128) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


ALTER TABLE `address`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `address` (`address`);

ALTER TABLE `data`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `bid` (`bid`), ADD KEY `address` (`address`);


ALTER TABLE `address`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
ALTER TABLE `data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
