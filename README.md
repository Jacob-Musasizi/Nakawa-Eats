NAKAWA RESTAURANTS ORDER AND DELIVERY MANAGEMENT SYSTEM
A web-based system for restaurants in Nakawa. Customers order at a self-service
kiosk for either dine in or take away and get an order token, or order online for delivery.
Waiters process the orders, and a token board calls customers when food is ready.
Start Apache and MySQL in xampp control panle
Unzip the restaurant-web folder into C:\xampp\htdocs\ 
 Open http://localhost/phpmyadmin and import the database order_system.sql which is in the system Zip folder
 Open http://localhost/restaurant-web/
 PAGES (Home, Kiosk, Order board, Delivery shop, Staff and owner login)
 TEST LOGINS
 Delivery Order:
 Restaurant owner(Mr. Hungry Fried chicken): cobzja548@gmail.com  password: ja#20Bz0
 Waiter: annest@gmail.com  password: ja#20Bz0
 Customer: jackie@gmail.com   password: ja#20Bz0
 Kiosk Order(dine in)
 Restaurant owner(JOJO' Foodies): angel@gmail.com  password: ja#20Bz0
 Waiter: clinton@gmail.com  password: ja#20Bz0
 HOW TO TEST
1. For dine in and take away, open the kiosk, choose dine in or take away, add dishes, place the order and note the token.
2. Log in at admin.html as the waiter. Click "Start preparing", then "Ready: call customer". The kiosk and board flash and sound the alarm.
3. For a delivery, log in at shop.html as the customer, place a delivery order, and pay for your order at checkout.
4. In admin.html, move the delivery order through to "Delivered".
