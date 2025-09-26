**Easy Way to Update Product Prices in WooCommerce**

https://yoursite.com/?increase=10
// Increase all products (simple + variable) by 10%

https://yoursite.com/?decrease=15
// Decrease all products (simple + variable) by 15%

https://yoursite.com/?increase=10&round=up
// Increase all products by 10% and round final prices up to the default base (1000)

https://yoursite.com/?increase=10&round=down
// Increase all products by 10% and round final prices down to the default base (1000)

https://yoursite.com/?increase=10&round=up&roundbase=10000
// Increase all products by 10% and round prices up to the nearest 10,000

https://yoursite.com/?decrease=20&round=down&roundbase=5000
// Decrease all products by 20% and round prices down to the nearest 5,000

https://yoursite.com/?increase=12&saleprice=true
// Increase all products by 12% and also update sale prices (if they exist)

https://yoursite.com/?decrease=8&saleprice=true
// Decrease all products by 8% and also update sale prices (if they exist)

https://yoursite.com/?increase=10&product=simple
// Increase only simple products by 10%

https://yoursite.com/?decrease=5&product=variable
// Decrease only variable products by 5%

https://yoursite.com/?increase=7&product=all
// Increase all products (simple + variable) by 7% (default mode)

https://yoursite.com/?increase=10&saleprice=true&product=simple
// Increase only simple products by 10% including their sale prices

https://yoursite.com/?decrease=15&saleprice=true&product=variable
// Decrease only variable products by 15% including their sale prices

https://yoursite.com/?increase=5&offset=0&limit=50
// Increase first batch of 50 products by 5%

https://yoursite.com/?increase=5&offset=50&limit=50
// Increase the second batch (products 51–100) by 5%

https://yoursite.com/?increase=5&offset=100&limit=50
// Increase the third batch (products 101–150) by 5%

https://yoursite.com/?decrease=10&round=up&roundbase=1000&saleprice=true&product=all&offset=0&limit=100
// Full example: decrease all products (simple + variable) by 10%, 
// round prices up to nearest 1000, update sale prices, 
// process only the first 100 products
