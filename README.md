php-wkhtmltopdf
===============

A PHP class to generate PDF using webkit based wkhtmltopdf generator. This one supports auto table break (with table header on each page) on page breaks.

wkhtmltopdf does not support auto table break (on page breaks) for long tables. As a result 
a long table gets printed on multiple pages without properly breaking the table (putting table 
header at the beginning of each page) on each page. However wkhtmltopdf does support force 
page break (using css) and therefore developers can handle long tables by inserting a force page 
break where appropriate. This works well for a static table where the developers know for sure 
that their table is going to take 'X' number of pages. But it does not work when the table is 
dynamic and the developers don't exactly know how many pages their table will take. This is the main 
reason for me to write this PHP interface for wkhtmltopdf so that it can automatically break a 
long table properly (with header) on each page. This is done by simply counting how many rows 
a page can fit and then break it accordingly.