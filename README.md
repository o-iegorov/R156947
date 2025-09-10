# R156947

Coding challenge

# Installation

Download source code from the repository `https://github.com/o-iegorov/R156947.git` and install dependencies using Composer:

```bash
composer install
```
# Usage

To remove duplicates from source file use cli command:

```bash
bin/parser  json:deduplicate -o OutputFile.json
```

As source command will use `leads.json` file. The filename may be configured by passing `-i leads.json` option to the command.

# Logging

Logging is implemented using Monolog library. Logs are stored in `var/logs/application.log` file. 