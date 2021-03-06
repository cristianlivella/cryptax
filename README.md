# CrypTax
Software for calculating cryptocurrency capital gains for tax purposes. Calculation with LIFO method, developed with Italy in mind.

![Screencast](./screencast.gif)  
*The screencast is made with random transactions. They are not my holdings.*

## ⚠️ Please read here before use
- This is just the software I use for my tax reports. It's not perfect and it's not production ready, use it at your own risk.
- At the moment in Italy there is no clear regulation regarding the declaration and taxation for cryptocurrencies and capital gains, so some of the logic used in this software could come from my interpretations. I take no responsibility about it. Always DYOR (Do Your Own Research).

## ℹ️ What this software does
At the moment, the features of this software are quite limited, but they are the most inconvenient and time-consuming to do manually, at least in my case.

In fact, it is just a script that takes in input a csv file with all your transactions, and elaborates a quite complete and printable report. In the future I plan to make it a more complete software, maybe even with a nice front-end.

## ❓ How to use it
- Put the project folder in a web server with PHP;
- create a copy of the file `config.sample.php` in `config.php`, and set inside it the credentials of the MySQL database (it will be used as a cache for cryptocurrency prices) and the location of the `transactions.csv` file;
- visit `index.php` through a web browser.

## 📄 The transactions.csv file
- The csv file must have 7 columns, separated by semicolon `;`.  
- The file must not have a header row.

This is the description of the fields:
1. **transaction_date**: in dd/mm/yyyy format
2. **transaction type**: `purchase`, `sale` or `expense` (or the correspondents in Italian: `acquisto`, `vendita` or `spesa`)
3. **EUR value**: the value of the transaction in euros, including commissions
4. **cryptocurrency amount**: amount of cryptocurrency bought, sold or spent; without thousands separators, using the dot `.` as decimal separator
5. **cryptocurrency ticker**: usually a 3 characters string, like `BTC`, `ETH` or `BNB`
6. **exchange**: name of the exchange where the buy or sell has been done; this is only for the volume chart, you can leave it empty if you are not interested
7. **earning category**: for `purchase`/`acquisto` type transactions with price = 0, you can set the earning category; it affects some calculations in the final report

### Earning categories
- `airdrop`: a capital gain is calculated equal to the value of the cryptocurrency on the day of the transaction
- `interessi`/`interest`: are considered as *redditi di capitale*, taxed at a rate of 26%
- `cashback`: the value at the day of the transaction is not taxed, but only the eventual capital gain at the time of the sale


**please note**: these are the considerations this software does, but they may not be the correct ones! DYOR and/or consult an expert!
