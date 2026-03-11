<?php \ = new PDO('sqlite:database/database.sqlite'); \ = \->query('PRAGMA table_info(users)'); var_dump(\->fetchAll(PDO::FETCH_ASSOC));
