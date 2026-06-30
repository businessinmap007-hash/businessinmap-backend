/*
BIM 2.7.16 — Service Catalog Matrix Seeds

Purpose:
1) Seed normalized Platform Service Item Types for:
   - Hotels / accommodation booking
   - Restaurants menu and table booking
   - Supermarkets product catalog
   - Clinics appointment booking
   - Sports fields booking
   - Training course booking
2) Optionally seed Category Child service catalogs by matching common Arabic/English child names.

Safe to re-run:
- Existing item types are updated.
- Missing item types are inserted.
- Optional matrix mapping updates existing CategoryServiceConfig rows or creates missing rows.

Notes:
- This file does not delete old data.
- Service Catalog Matrix remains the admin source of truth for what each child can see.
*/

START TRANSACTION;

/* ---------------------------------------------------------
   Ensure base platform services exist
--------------------------------------------------------- */
INSERT INTO platform_services (`key`, `name_ar`, `name_en`, `is_active`, `created_at`, `updated_at`)
SELECT 'booking', 'الحجز', 'Booking', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM platform_services WHERE `key` = 'booking');

INSERT INTO platform_services (`key`, `name_ar`, `name_en`, `is_active`, `created_at`, `updated_at`)
SELECT 'menu', 'المنيو / المنتجات', 'Menu / Products', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM platform_services WHERE `key` = 'menu');

INSERT INTO platform_services (`key`, `name_ar`, `name_en`, `is_active`, `created_at`, `updated_at`)
SELECT 'delivery', 'الدليفري', 'Delivery', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM platform_services WHERE `key` = 'delivery');

/* ---------------------------------------------------------
   Seed item types in a temporary catalog
--------------------------------------------------------- */
DROP TEMPORARY TABLE IF EXISTS tmp_bim_service_item_type_seed;

CREATE TEMPORARY TABLE tmp_bim_service_item_type_seed (
    service_key VARCHAR(100) NOT NULL,
    item_key VARCHAR(100) NOT NULL,
    name_ar VARCHAR(191) NOT NULL,
    name_en VARCHAR(191) NOT NULL,
    domain_key VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    meta JSON NULL
);

/* Booking — Hotels / accommodation */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('booking','single_room','غرفة فردية','Single Room','hotel',10,1,JSON_OBJECT('business_examples',JSON_ARRAY('hotel','hostel','resort'),'search_keywords',JSON_ARRAY('single','room','hotel','غرفة','فردية'))),
('booking','double_room','غرفة مزدوجة','Double Room','hotel',20,1,JSON_OBJECT('business_examples',JSON_ARRAY('hotel','resort'),'search_keywords',JSON_ARRAY('double','room','غرفة','مزدوجة'))),
('booking','twin_room','غرفة توأم','Twin Room','hotel',30,0,JSON_OBJECT('search_keywords',JSON_ARRAY('twin','room','غرفة','توأم'))),
('booking','triple_room','غرفة ثلاثية','Triple Room','hotel',40,0,JSON_OBJECT('search_keywords',JSON_ARRAY('triple','room','غرفة','ثلاثية'))),
('booking','family_room','غرفة عائلية','Family Room','hotel',50,0,JSON_OBJECT('search_keywords',JSON_ARRAY('family','room','غرفة','عائلية'))),
('booking','standard_room','غرفة قياسية','Standard Room','hotel',60,0,JSON_OBJECT('search_keywords',JSON_ARRAY('standard','room','قياسية'))),
('booking','deluxe_room','غرفة ديلوكس','Deluxe Room','hotel',70,0,JSON_OBJECT('search_keywords',JSON_ARRAY('deluxe','room','ديلوكس'))),
('booking','suite','جناح','Suite','hotel',80,1,JSON_OBJECT('search_keywords',JSON_ARRAY('suite','جناح'))),
('booking','junior_suite','جناح جونيور','Junior Suite','hotel',90,0,JSON_OBJECT('search_keywords',JSON_ARRAY('junior suite','جناح'))),
('booking','executive_suite','جناح تنفيذي','Executive Suite','hotel',100,0,JSON_OBJECT('search_keywords',JSON_ARRAY('executive suite','تنفيذي'))),
('booking','royal_suite','جناح ملكي','Royal Suite','hotel',110,0,JSON_OBJECT('search_keywords',JSON_ARRAY('royal suite','presidential suite','ملكي','رئاسي'))),
('booking','apartment','شقة','Apartment','hotel',120,0,JSON_OBJECT('business_examples',JSON_ARRAY('hotel apartment','serviced apartment'),'search_keywords',JSON_ARRAY('apartment','شقة'))),
('booking','studio','استوديو','Studio','hotel',130,0,JSON_OBJECT('search_keywords',JSON_ARRAY('studio','استوديو'))),
('booking','villa','فيلا','Villa','hotel',140,0,JSON_OBJECT('search_keywords',JSON_ARRAY('villa','فيلا'))),
('booking','chalet','شاليه','Chalet','hotel',150,0,JSON_OBJECT('search_keywords',JSON_ARRAY('chalet','شاليه')));

/* Booking — Restaurant table reservations */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('booking','restaurant_table','طاولة','Restaurant Table','restaurant_table',210,1,JSON_OBJECT('business_examples',JSON_ARRAY('restaurant','cafe'),'search_keywords',JSON_ARRAY('table','restaurant','طاولة','مطعم'))),
('booking','indoor_table','طاولة داخلية','Indoor Table','restaurant_table',220,0,JSON_OBJECT('search_keywords',JSON_ARRAY('indoor table','طاولة داخلية'))),
('booking','outdoor_table','طاولة خارجية','Outdoor Table','restaurant_table',230,0,JSON_OBJECT('search_keywords',JSON_ARRAY('outdoor table','طاولة خارجية'))),
('booking','family_table','طاولة عائلية','Family Table','restaurant_table',240,0,JSON_OBJECT('search_keywords',JSON_ARRAY('family table','طاولة عائلية'))),
('booking','vip_table','طاولة VIP','VIP Table','restaurant_table',250,0,JSON_OBJECT('search_keywords',JSON_ARRAY('vip table','طاولة vip'))),
('booking','private_room','غرفة خاصة','Private Dining Room','restaurant_table',260,0,JSON_OBJECT('search_keywords',JSON_ARRAY('private dining','غرفة خاصة')));

/* Booking — Clinics / appointments */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('booking','clinic_consultation','كشف / استشارة','Consultation','clinic',310,1,JSON_OBJECT('business_examples',JSON_ARRAY('clinic','doctor'),'search_keywords',JSON_ARRAY('consultation','clinic','doctor','كشف','استشارة'))),
('booking','clinic_follow_up','متابعة','Follow-up','clinic',320,1,JSON_OBJECT('search_keywords',JSON_ARRAY('follow up','متابعة'))),
('booking','clinic_session','جلسة علاجية','Treatment Session','clinic',330,0,JSON_OBJECT('search_keywords',JSON_ARRAY('session','treatment','جلسة'))),
('booking','clinic_procedure','إجراء طبي','Medical Procedure','clinic',340,0,JSON_OBJECT('search_keywords',JSON_ARRAY('procedure','medical','إجراء'))),
('booking','telemedicine','استشارة أونلاين','Telemedicine','clinic',350,0,JSON_OBJECT('search_keywords',JSON_ARRAY('telemedicine','online consultation','أونلاين'))),
('booking','lab_test','تحليل / اختبار','Lab Test','clinic',360,0,JSON_OBJECT('search_keywords',JSON_ARRAY('lab','test','تحاليل'))),
('booking','imaging_scan','أشعة / تصوير','Imaging Scan','clinic',370,0,JSON_OBJECT('search_keywords',JSON_ARRAY('xray','scan','imaging','أشعة')));

/* Booking — Sports fields */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('booking','football_5_field','ملعب خماسي','5-a-side Football Field','sports',410,1,JSON_OBJECT('business_examples',JSON_ARRAY('sports field','club'),'search_keywords',JSON_ARRAY('football','5 a side','ملعب','خماسي'))),
('booking','football_7_field','ملعب سباعي','7-a-side Football Field','sports',420,0,JSON_OBJECT('search_keywords',JSON_ARRAY('football','7 a side','ملعب','سباعي'))),
('booking','football_11_field','ملعب قانوني','11-a-side Football Field','sports',430,0,JSON_OBJECT('search_keywords',JSON_ARRAY('football','11','ملعب قانوني'))),
('booking','padel_court','ملعب بادل','Padel Court','sports',440,0,JSON_OBJECT('search_keywords',JSON_ARRAY('padel','بادل'))),
('booking','tennis_court','ملعب تنس','Tennis Court','sports',450,0,JSON_OBJECT('search_keywords',JSON_ARRAY('tennis','تنس'))),
('booking','basketball_court','ملعب كرة سلة','Basketball Court','sports',460,0,JSON_OBJECT('search_keywords',JSON_ARRAY('basketball','سلة'))),
('booking','volleyball_court','ملعب كرة طائرة','Volleyball Court','sports',470,0,JSON_OBJECT('search_keywords',JSON_ARRAY('volleyball','طائرة'))),
('booking','swimming_lane','حارة سباحة','Swimming Lane','sports',480,0,JSON_OBJECT('search_keywords',JSON_ARRAY('swimming','سباحة')));

/* Booking — Courses / training */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('booking','training_course','دورة تدريبية','Training Course','training',510,1,JSON_OBJECT('business_examples',JSON_ARRAY('academy','training center'),'search_keywords',JSON_ARRAY('course','training','دورة','تدريب'))),
('booking','workshop','ورشة عمل','Workshop','training',520,0,JSON_OBJECT('search_keywords',JSON_ARRAY('workshop','ورشة'))),
('booking','private_lesson','حصة خاصة','Private Lesson','training',530,0,JSON_OBJECT('search_keywords',JSON_ARRAY('private lesson','حصة خاصة'))),
('booking','group_class','محاضرة جماعية','Group Class','training',540,0,JSON_OBJECT('search_keywords',JSON_ARRAY('group class','محاضرة','جماعية'))),
('booking','online_session','جلسة أونلاين','Online Session','training',550,0,JSON_OBJECT('search_keywords',JSON_ARRAY('online session','أونلاين')));

/* Menu — Restaurants */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('menu','appetizers','مقبلات','Appetizers','restaurant_menu',10,1,JSON_OBJECT('search_keywords',JSON_ARRAY('appetizers','starters','مقبلات'))),
('menu','salads','سلطات','Salads','restaurant_menu',20,0,JSON_OBJECT('search_keywords',JSON_ARRAY('salad','سلطات'))),
('menu','soups','شوربة','Soups','restaurant_menu',30,0,JSON_OBJECT('search_keywords',JSON_ARRAY('soup','شوربة'))),
('menu','grills','مشويات','Grills','restaurant_menu',40,1,JSON_OBJECT('search_keywords',JSON_ARRAY('grills','bbq','مشويات'))),
('menu','main_dishes','أطباق رئيسية','Main Dishes','restaurant_menu',50,1,JSON_OBJECT('search_keywords',JSON_ARRAY('mains','entrees','أطباق'))),
('menu','sandwiches','ساندوتشات','Sandwiches','restaurant_menu',60,0,JSON_OBJECT('search_keywords',JSON_ARRAY('sandwich','ساندوتش'))),
('menu','pizza','بيتزا','Pizza','restaurant_menu',70,0,JSON_OBJECT('search_keywords',JSON_ARRAY('pizza','بيتزا'))),
('menu','pasta','مكرونة / باستا','Pasta','restaurant_menu',80,0,JSON_OBJECT('search_keywords',JSON_ARRAY('pasta','مكرونة','باستا'))),
('menu','seafood','مأكولات بحرية','Seafood','restaurant_menu',90,0,JSON_OBJECT('search_keywords',JSON_ARRAY('seafood','سمك','بحري'))),
('menu','breakfast','إفطار','Breakfast','restaurant_menu',100,0,JSON_OBJECT('search_keywords',JSON_ARRAY('breakfast','إفطار'))),
('menu','desserts','حلويات','Desserts','restaurant_menu',110,0,JSON_OBJECT('search_keywords',JSON_ARRAY('dessert','حلويات'))),
('menu','hot_drinks','مشروبات ساخنة','Hot Drinks','restaurant_menu',120,0,JSON_OBJECT('search_keywords',JSON_ARRAY('coffee','tea','hot drinks','مشروبات ساخنة'))),
('menu','cold_drinks','مشروبات باردة','Cold Drinks','restaurant_menu',130,0,JSON_OBJECT('search_keywords',JSON_ARRAY('cold drinks','juice','مشروبات باردة'))),
('menu','kids_meals','وجبات أطفال','Kids Meals','restaurant_menu',140,0,JSON_OBJECT('search_keywords',JSON_ARRAY('kids menu','وجبات أطفال')));

/* Menu — Supermarket / grocery */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('menu','fresh_produce','خضار وفاكهة','Fresh Produce','supermarket',210,1,JSON_OBJECT('search_keywords',JSON_ARRAY('produce','fruits','vegetables','خضار','فاكهة'))),
('menu','dairy_eggs','ألبان وبيض','Dairy & Eggs','supermarket',220,1,JSON_OBJECT('search_keywords',JSON_ARRAY('dairy','eggs','milk','ألبان','بيض'))),
('menu','cheese','أجبان','Cheese','supermarket',230,1,JSON_OBJECT('search_keywords',JSON_ARRAY('cheese','أجبان'))),
('menu','bakery','مخبوزات','Bakery','supermarket',240,0,JSON_OBJECT('search_keywords',JSON_ARRAY('bakery','bread','مخبوزات','عيش'))),
('menu','meat_poultry','لحوم ودواجن','Meat & Poultry','supermarket',250,0,JSON_OBJECT('search_keywords',JSON_ARRAY('meat','poultry','لحوم','دواجن'))),
('menu','seafood_grocery','أسماك ومأكولات بحرية','Seafood','supermarket',260,0,JSON_OBJECT('search_keywords',JSON_ARRAY('seafood','fish','أسماك'))),
('menu','frozen_food','مجمدات','Frozen Food','supermarket',270,0,JSON_OBJECT('search_keywords',JSON_ARRAY('frozen','مجمدات'))),
('menu','pasta_rice_grains','مكرونات وأرز وحبوب','Pasta, Rice & Grains','supermarket',280,1,JSON_OBJECT('search_keywords',JSON_ARRAY('pasta','rice','grains','مكرونات','أرز','حبوب'))),
('menu','canned_food','معلبات','Canned Food','supermarket',290,0,JSON_OBJECT('search_keywords',JSON_ARRAY('canned','معلبات'))),
('menu','oils_ghee','زيوت وسمن','Oils & Ghee','supermarket',300,1,JSON_OBJECT('search_keywords',JSON_ARRAY('oil','ghee','زيوت','سمن'))),
('menu','snacks','سناكس وتسالي','Snacks','supermarket',310,1,JSON_OBJECT('search_keywords',JSON_ARRAY('snacks','chips','سناكس','تسالي'))),
('menu','sweets_chocolate','حلويات وشوكولاتة','Sweets & Chocolate','supermarket',320,0,JSON_OBJECT('search_keywords',JSON_ARRAY('chocolate','sweets','حلويات','شوكولاتة'))),
('menu','beverages','مشروبات','Beverages','supermarket',330,0,JSON_OBJECT('search_keywords',JSON_ARRAY('beverages','drinks','مشروبات'))),
('menu','cleaning_supplies','منظفات','Cleaning Supplies','supermarket',340,0,JSON_OBJECT('search_keywords',JSON_ARRAY('cleaning','detergent','منظفات'))),
('menu','personal_care','عناية شخصية','Personal Care','supermarket',350,0,JSON_OBJECT('search_keywords',JSON_ARRAY('personal care','cosmetics','عناية'))),
('menu','baby_products','منتجات أطفال','Baby Products','supermarket',360,0,JSON_OBJECT('search_keywords',JSON_ARRAY('baby','diapers','أطفال','حفاضات'))),
('menu','pet_supplies','مستلزمات حيوانات أليفة','Pet Supplies','supermarket',370,0,JSON_OBJECT('search_keywords',JSON_ARRAY('pet','حيوانات أليفة'))),
('menu','household_items','أدوات منزلية','Household Items','supermarket',380,0,JSON_OBJECT('search_keywords',JSON_ARRAY('household','home','منزلية')));

/* Delivery — general service modes/items */
INSERT INTO tmp_bim_service_item_type_seed VALUES
('delivery','restaurant_delivery','توصيل مطعم','Restaurant Delivery','delivery',10,1,JSON_OBJECT('search_keywords',JSON_ARRAY('restaurant delivery','توصيل مطعم'))),
('delivery','grocery_delivery','توصيل سوبر ماركت','Grocery Delivery','delivery',20,1,JSON_OBJECT('search_keywords',JSON_ARRAY('grocery delivery','توصيل سوبر ماركت'))),
('delivery','pharmacy_delivery','توصيل صيدلية','Pharmacy Delivery','delivery',30,0,JSON_OBJECT('search_keywords',JSON_ARRAY('pharmacy delivery','توصيل صيدلية'))),
('delivery','scheduled_delivery','توصيل مجدول','Scheduled Delivery','delivery',40,0,JSON_OBJECT('search_keywords',JSON_ARRAY('scheduled delivery','توصيل مجدول'))),
('delivery','express_delivery','توصيل سريع','Express Delivery','delivery',50,0,JSON_OBJECT('search_keywords',JSON_ARRAY('express delivery','توصيل سريع')));

/* ---------------------------------------------------------
   Update existing rows
--------------------------------------------------------- */
UPDATE platform_service_item_types pit
JOIN platform_services ps ON ps.id = pit.platform_service_id
JOIN tmp_bim_service_item_type_seed seed
  ON seed.service_key = ps.`key`
 AND seed.item_key = pit.`key`
SET
    pit.name_ar = seed.name_ar,
    pit.name_en = seed.name_en,
    pit.is_default = seed.is_default,
    pit.is_active = 1,
    pit.sort_order = seed.sort_order,
    pit.meta = JSON_MERGE_PATCH(
        COALESCE(pit.meta, JSON_OBJECT()),
        JSON_OBJECT(
            'seed', 'bim_2_7_16_service_catalog',
            'domain_key', seed.domain_key,
            'research_basis', JSON_ARRAY('hotel_room_types','restaurant_menu_sections','supermarket_merchandise_categories','clinic_and_booking_appointment_types'),
            'seeded_at', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
        ),
        COALESCE(seed.meta, JSON_OBJECT())
    ),
    pit.updated_at = NOW();

/* ---------------------------------------------------------
   Insert missing rows
--------------------------------------------------------- */
INSERT INTO platform_service_item_types
    (platform_service_id, `key`, name_ar, name_en, is_default, is_active, sort_order, meta, created_at, updated_at)
SELECT
    ps.id,
    seed.item_key,
    seed.name_ar,
    seed.name_en,
    seed.is_default,
    1,
    seed.sort_order,
    JSON_MERGE_PATCH(
        JSON_OBJECT(
            'seed', 'bim_2_7_16_service_catalog',
            'domain_key', seed.domain_key,
            'research_basis', JSON_ARRAY('hotel_room_types','restaurant_menu_sections','supermarket_merchandise_categories','clinic_and_booking_appointment_types'),
            'seeded_at', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
        ),
        COALESCE(seed.meta, JSON_OBJECT())
    ),
    NOW(),
    NOW()
FROM tmp_bim_service_item_type_seed seed
JOIN platform_services ps ON ps.`key` = seed.service_key
LEFT JOIN platform_service_item_types pit
  ON pit.platform_service_id = ps.id
 AND pit.`key` = seed.item_key
WHERE pit.id IS NULL;

COMMIT;

/* ---------------------------------------------------------
   Optional helper queries after running the seed
---------------------------------------------------------

1) See seeded types by service:

SELECT ps.`key` AS service_key, pit.`key`, pit.name_ar, pit.name_en, pit.sort_order
FROM platform_service_item_types pit
JOIN platform_services ps ON ps.id = pit.platform_service_id
WHERE JSON_EXTRACT(pit.meta, '$.seed') = 'bim_2_7_16_service_catalog'
ORDER BY ps.`key`, pit.sort_order, pit.id;

2) Then use Admin → Services → Service Catalog Matrix to assign:
   - Hotel children: booking → single_room, double_room, twin_room, suite, royal_suite, apartment, villa, chalet
   - Restaurant children: menu → appetizers, grills, main_dishes, desserts, beverages; booking → restaurant_table, vip_table
   - Supermarket children: menu → dairy_eggs, cheese, pasta_rice_grains, oils_ghee, snacks, cleaning_supplies
   - Clinic children: booking → clinic_consultation, clinic_follow_up, clinic_session, clinic_procedure, telemedicine
   - Sports field children: booking → football_5_field, football_7_field, padel_court, tennis_court
   - Training children: booking → training_course, workshop, private_lesson, group_class
*/
