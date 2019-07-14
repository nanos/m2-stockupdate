# StockUpdate

A really basic module that will update your product stock from a CSV file every 10 minutes.

## Usage

Install the module, then place a file called `stock.csv` into the `var/import/` folder.

The module will

1. Check the file exists 
2. If there is a file already been processed wait until this is finished & successful
3. Prevent empty files being processed
4. Process file and update new stock values
5. Ensure In Stock & Out Of Stock settings are triggered 
6. Manage indexes & cache
7. Provide detailed logging

## CSV file format

 - The csv file needs to be put into `var/import/stock.csv`.
 - Each row must have two columns: First column is the SKU, second the quantity
 - Columns are delimted by pipe `|`.

```
sku1 | 4
sku2 | 0
```

## Limitations

 - To ensure we are not running the update while another process is running we are storing the start and end date/time of each run in the database. This is clunky because
    1) It can easily create problems, e.g. when an error gets thrown, or another un-anticipated edge case ocurs, and we aren't updating the `end_time` column.
    2) The table will fill up really quickly. You'll want to ensure that it get's cleaned out regularly.
    3) We should be able to rely on Magento's built in cron mechanism which should ensure that no two instances of the same cron job can run concurrently. (Although that can also suffer of the first problem.)
 - No checks are made to ensure that `stock.csv` is "new". Before using this, you'll definitely want to make sure that you check the modified timestamp of the file against a record of the last run and proceed only if newer.
 - All parameters (filenames, folders, schedule) are hard coded.
 - Rows that don't contain exactly two parameters, as well as rows that contain invalid SKU or quantity will be skipped.