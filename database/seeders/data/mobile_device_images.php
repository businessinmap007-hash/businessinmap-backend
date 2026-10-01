<?php

/*
| Open-licensed photos of the device catalog — «اذا كان هناك صور مفتوحة
| المصدر اضفها فى بيانات الموبايل» (المالك، 2026-10-01).
|
| Found through each model's Wikidata item (its P18 image) and served from
| Wikimedia Commons. Only CC BY / CC BY-SA / CC0 / public-domain files are
| listed, each with the credit its licence asks for — stored beside the URL
| in catalog_products.main_image_credit. A model missing here simply had no
| photo on Commons; it is never filled with a guess.
|
| Reviewed by eye, photo by photo: a shop shot showing another store's
| price tags (Moto G54, Galaxy A56, Xiaomi 14T, Redmi Pad Pro) and a
| settings-screen screenshot (Oppo Find X8) were left out.
|
| `php artisan catalog:import-device-images` downloads each file to
| public/files/uploads/catalog-devices/ and points main_image at the local
| copy, so the app never depends on Wikimedia being reachable.
|
| name_en => [image url, credit]. Applied by MobileDeviceCatalogSeeder, which
| never overwrites an image somebody else set.
*/

return [
    'Apple Watch Series 10 46mm' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/0/06/Apple_Watch_Series_10.png/500px-Apple_Watch_Series_10.png', 'KK IN HK — Public domain, Wikimedia Commons'],
    'Apple iPhone 14 Pro 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9f/IPhone_14_Pro_vector.svg/500px-IPhone_14_Pro_vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 14 Pro Max 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9f/IPhone_14_Pro_vector.svg/500px-IPhone_14_Pro_vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 15 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/e/ee/IPhone_15_Vector.svg/500px-IPhone_15_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 15 Plus 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2b/IPhone_15_Plus.jpeg/500px-IPhone_15_Plus.jpeg', 'Ka Kit Pang — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 15 Pro 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f5/IPhone_15_Pro_Vector.svg/500px-IPhone_15_Pro_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 15 Pro Max 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/5/5f/IPhone_15_pro_max.png/500px-IPhone_15_pro_max.png', 'Ka Kit Pang — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 16 Plus 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/7/75/IPhone_16_Plus_Vector.svg/500px-IPhone_16_Plus_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 16 Pro Max 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fc/IPhone_16_Pro_Max_Vector.svg/500px-IPhone_16_Pro_Max_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 16e 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/5/5d/IPhone_16e_Vector.svg/500px-IPhone_16e_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone 17 Pro Max 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2f/IPhone_17_Pro_Max_Vector.svg/500px-IPhone_17_Pro_Max_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Apple iPhone Air 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/20/IPhone_Air_Vector.svg/500px-IPhone_Air_Vector.svg.png', 'Rafael Fernandez — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A05s 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a2/Samsung_Galaxy_A05s_2024_%281%29_%28cropped%29.jpg/500px-Samsung_Galaxy_A05s_2024_%281%29_%28cropped%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A06 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0a/Samsung_Galaxy_A06_5G_2025.jpg/500px-Samsung_Galaxy_A06_5G_2025.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A16 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/29/Samsung_Galaxy_A16_5G_2025_%28cropped%29.jpg/500px-Samsung_Galaxy_A16_5G_2025_%28cropped%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A24 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c4/%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_A24_%28cropped%29.jpg/500px-%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_A24_%28cropped%29.jpg', 'Striker9498 — CC BY-SA 3.0, Wikimedia Commons'],
    'Samsung Galaxy A25 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/26/Samsung_Galaxy_A25_5G_2024_%281%29_%28cropped%29.jpg/500px-Samsung_Galaxy_A25_5G_2024_%281%29_%28cropped%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A26 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a2/Samsung_Galaxy_A26_5G_2025.jpg/500px-Samsung_Galaxy_A26_5G_2025.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A34 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/b/ba/%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_A34_%28cropped%29.jpg/500px-%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_A34_%28cropped%29.jpg', 'Striker9498 — CC BY-SA 3.0, Wikimedia Commons'],
    'Samsung Galaxy A35 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/c/ca/Samsung_Galaxy_A35_5G_2024_%281%29.jpg/500px-Samsung_Galaxy_A35_5G_2024_%281%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A36 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d5/Samsung_Galaxy_A36_5G_2025.jpg/500px-Samsung_Galaxy_A36_5G_2025.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A54 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/9/95/Back_of_the_Samsung_Galaxy_A54_5G.jpg/500px-Back_of_the_Samsung_Galaxy_A54_5G.jpg', 'Hajoon0102 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy A55 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fe/Samsung_Galaxy_A55_5G_2024_%28cropped%29.jpg/500px-Samsung_Galaxy_A55_5G_2024_%28cropped%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy M35 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/1/18/Galaxy_M35_front.jpg/500px-Galaxy_M35_front.jpg', 'Maksdroider — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S23 256GB' => ['https://upload.wikimedia.org/wikipedia/commons/8/83/Galaxy_S23_%28cropped%29.png', 'Hajoon0102 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S24 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/0/05/Samsung_Galaxy_S24%2C_Sperrbildschirm.JPG/500px-Samsung_Galaxy_S24%2C_Sperrbildschirm.JPG', 'C.Stadler/Bwag — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S24 FE 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/8/81/Samsung_Galaxy_S24_FE_2024_%28cropped%29.jpg/500px-Samsung_Galaxy_S24_FE_2024_%28cropped%29.jpg', 'Captainmorlypogi1959 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S24 Ultra 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/e/ed/Samsung_S24_Ultra_Phone.png/500px-Samsung_S24_Ultra_Phone.png', 'FarihF10 — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S24+ 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/c/cb/Samsung_Galaxy_S24%2B.jpg/500px-Samsung_Galaxy_S24%2B.jpg', 'Vis M — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S25 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/e/ea/Galaxy_S25_Black.png/500px-Galaxy_S25_Black.png', 'Mandy Harper — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S25 Ultra 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/2/25/Samsung_Galaxy_S25_Ultra_Titanium_Silverblue.jpg/500px-Samsung_Galaxy_S25_Ultra_Titanium_Silverblue.jpg', 'Alvis Jean — CC BY-SA 4.0, Wikimedia Commons'],
    'Samsung Galaxy S25+ 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fd/20250124_%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_s25%2B.jpg/500px-20250124_%EC%82%BC%EC%84%B1_%EA%B0%A4%EB%9F%AD%EC%8B%9C_s25%2B.jpg', 'Striker9498 — CC0, Wikimedia Commons'],
    'Samsung Galaxy Tab S9 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/0/05/Samsung_Galaxy_Tab_S9.png/500px-Samsung_Galaxy_Tab_S9.png', 'AnVuong1222004 (11) — CC BY-SA 4.0, Wikimedia Commons'],
    'Xiaomi 13T 256GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/7/72/Xiaomi_13T_XIG04%28%E3%82%A2%E3%83%AB%E3%83%91%E3%82%A4%E3%83%B3%E3%83%96%E3%83%AB%E3%83%BC%29.jpg/500px-Xiaomi_13T_XIG04%28%E3%82%A2%E3%83%AB%E3%83%91%E3%82%A4%E3%83%B3%E3%83%96%E3%83%AB%E3%83%BC%29.jpg', 'けいあ — CC BY-SA 4.0, Wikimedia Commons'],
    'Xiaomi Pad 6 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f0/Xiaomi_Pad_6_display.jpg/500px-Xiaomi_Pad_6_display.jpg', 'Myrat — CC0, Wikimedia Commons'],
    'Xiaomi Redmi 12 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/7/73/Redmi_12_front.jpg/500px-Redmi_12_front.jpg', 'Maksdroider — CC BY-SA 4.0, Wikimedia Commons'],
    'Xiaomi Redmi 13 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/3/39/Redmi_13_Product_photography_05.jpg/500px-Redmi_13_Product_photography_05.jpg', 'Wasiul Bahar — CC BY-SA 4.0, Wikimedia Commons'],
    'Xiaomi Redmi Note 12 128GB' => ['https://thumb.wikimedia.org/wikipedia/commons/thumb/b/be/Redmi_Note_12_front.jpg/500px-Redmi_Note_12_front.jpg', 'Maksdroider — CC BY-SA 4.0, Wikimedia Commons'],
];
