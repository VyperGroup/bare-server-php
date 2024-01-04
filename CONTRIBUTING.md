# CONTRIBUTING

To get linting support make sure you run

```sh
npm install --global prettier @prettier/plugin-php
```

OR

```sh
# Because intelliphp hasn't been ported to PHP 8
composer require --ignore-platform-req=php  devfym/intelliphp
```

If you use intelliphp, make sure you swap all instances of "@prettier/plugin-php" to "devsense.phptools-vscode"
