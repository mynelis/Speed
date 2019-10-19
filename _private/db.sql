set foreign_key_checks = 0;
set sql_mode = 'no_auto_value_on_zero';
set autocommit = 0;
start transaction;
set time_zone = '+00:00';

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ----------------------------------------------------------
-- category

drop table if exists `category`;
create table `category`
(
	-- Required system columns --
	`id` int(2) not null auto_increment,
	`bind` varchar(50) not null,

	-- Table columns --
	`name` varchar(100) not null,

	primary key (`id`)
)
engine = innodb default charset = utf8;

-- ----------------------------------------------------------
-- task

drop table if exists `task`;
create table `task`
(
	-- Required system columns --
	`id` int(11) not null auto_increment,
	`bind` varchar(50) not null,

	-- Table columns --
	`heading` varchar(100) not null,
	`description` varchar(100),
	`status` int(1) not null default 0,
	`date_completed` timestamp not null default current_timestamp,

	-- Foreign keys --
	`category_id` int(2) not null default 0,

	primary key (`id`)
)
engine = innodb default charset = utf8;

-- ----------------------------------------------------------
-- foreign keys

alter table `task`
	add constraint `fk_task_category` foreign key (`category_id`) references `category`(`id`) on delete no action;

-- ----------------------------------------------------------
-- 

set foreign_key_checks = 1;
commit;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;