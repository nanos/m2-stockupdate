# StockUpdate

A really basic module that will update your product stock from a CSV file every 10 minutes.

## Usage

Install the module, then place a file called `stock.csv` into the `var/import/` folder.

The module will

1. Check the file exists 
2. Prevent empty files being processed
3. Process file and update new stock values
4. Ensure In Stock & Out Of Stock settings are triggered 
5. Manage indexes & cache
6. Provide detailed logging

## CSV file format

 - The csv file needs to be put into `var/import/stock.csv`.
 - Each row must have two columns: First column is the SKU, second the quantity
 - Columns are delimted by pipe `|`.

```
sku1 | 4
sku2 | 0
```

## Limitations

 - The check if a file is already being processed and wait until this is finished and successful currently depends on the cron scheduler which prevents two cron jobs with the same name from running concurrently.
 - The way in which we clean the full page cache after updating cache seems really inelegant. Surely there must be a better way?!
 - No checks are made to ensure that `stock.csv` is "new". Before using this, you'll definitely want to make sure that you check the modified timestamp of the file against a record of the last run and proceed only if newer.
 - All parameters (filenames, folders, schedule) are hard coded.
 - Rows that don't contain exactly two parameters, as well as rows that contain invalid SKU or quantity will be skipped.