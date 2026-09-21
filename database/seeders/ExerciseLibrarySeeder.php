<?php

namespace Database\Seeders;

use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use Illuminate\Database\Seeder;

/**
 * The starter exercise catalogue. Add-only and idempotent (a category is keyed
 * on name_ar, an exercise on category + name_ar), so a trainer-side admin's
 * edits and additions survive a re-run.
 *
 * Each row: [name_ar, name_en, equipment, default sets, default reps].
 */
class ExerciseLibrarySeeder extends Seeder
{
    /** section => [name_ar, name_en, kind, rows] */
    private const SECTIONS = [
        'chest' => ['صدر', 'Chest', 'strength', [
            ['ضغط بار مسطح', 'Barbell bench press', 'barbell', 4, '8-10'],
            ['ضغط بار مائل', 'Incline barbell press', 'barbell', 4, '8-10'],
            ['ضغط دمبل مسطح', 'Dumbbell bench press', 'dumbbell', 4, '10-12'],
            ['ضغط دمبل مائل', 'Incline dumbbell press', 'dumbbell', 4, '10-12'],
            ['ضغط دمبل منحدر', 'Decline dumbbell press', 'dumbbell', 3, '10-12'],
            ['تفتيح دمبل', 'Dumbbell fly', 'dumbbell', 3, '12'],
            ['تفتيح كابل', 'Cable fly', 'cable', 3, '12-15'],
            ['بك دك (فراشة)', 'Pec deck', 'machine', 3, '12-15'],
            ['ضغط صدر جهاز', 'Machine chest press', 'machine', 3, '10-12'],
            ['بول أوفر', 'Dumbbell pullover', 'dumbbell', 3, '12'],
            ['تمرين الضغط', 'Push-up', 'bodyweight', 3, '15-20'],
            ['متوازي للصدر', 'Chest dips', 'bodyweight', 3, '8-12'],
        ]],
        'back' => ['ظهر', 'Back', 'strength', [
            ['عقلة عريضة', 'Wide-grip pull-up', 'bodyweight', 4, '6-10'],
            ['عقلة قبضة ضيقة', 'Close-grip chin-up', 'bodyweight', 3, '6-10'],
            ['سحب أمامي', 'Lat pulldown', 'machine', 4, '10-12'],
            ['سحب أمامي قبضة ضيقة', 'Close-grip lat pulldown', 'machine', 3, '10-12'],
            ['تجديف بار', 'Barbell row', 'barbell', 4, '8-10'],
            ['تجديف دمبل', 'One-arm dumbbell row', 'dumbbell', 3, '10-12'],
            ['تجديف كابل جلوس', 'Seated cable row', 'cable', 4, '10-12'],
            ['تجديف جهاز', 'Machine row', 'machine', 3, '10-12'],
            ['تي بار رو', 'T-bar row', 'barbell', 3, '10'],
            ['ديدلفت', 'Deadlift', 'barbell', 4, '5-8'],
            ['سحب مستقيم بالذراعين', 'Straight-arm pulldown', 'cable', 3, '12-15'],
            ['هايبر إكستنشن', 'Back extension', 'bodyweight', 3, '12-15'],
            ['شرقس (رفع الكتفين)', 'Barbell shrug', 'barbell', 4, '12'],
        ]],
        'shoulders' => ['أكتاف', 'Shoulders', 'strength', [
            ['ضغط أكتاف بار', 'Barbell overhead press', 'barbell', 4, '8-10'],
            ['ضغط أكتاف دمبل', 'Dumbbell shoulder press', 'dumbbell', 4, '10-12'],
            ['ضغط أكتاف جهاز', 'Machine shoulder press', 'machine', 3, '10-12'],
            ['رفرفة جانبية', 'Lateral raise', 'dumbbell', 4, '12-15'],
            ['رفرفة أمامية', 'Front raise', 'dumbbell', 3, '12'],
            ['رفرفة خلفية', 'Rear delt fly', 'dumbbell', 3, '12-15'],
            ['رفرفة جانبية كابل', 'Cable lateral raise', 'cable', 3, '12-15'],
            ['أرنولد برس', 'Arnold press', 'dumbbell', 3, '10-12'],
            ['سحب للوجه', 'Face pull', 'cable', 3, '15'],
            ['سحب بار للذقن', 'Upright row', 'barbell', 3, '10-12'],
        ]],
        'biceps' => ['ذراع أمامي (باي)', 'Biceps', 'strength', [
            ['كيرل بار', 'Barbell curl', 'barbell', 3, '10-12'],
            ['كيرل دمبل', 'Dumbbell curl', 'dumbbell', 3, '10-12'],
            ['كيرل مطرقة', 'Hammer curl', 'dumbbell', 3, '10-12'],
            ['كيرل مركّز', 'Concentration curl', 'dumbbell', 3, '12'],
            ['كيرل مائل', 'Incline dumbbell curl', 'dumbbell', 3, '10-12'],
            ['كيرل كابل', 'Cable curl', 'cable', 3, '12'],
            ['كيرل بريتشر', 'Preacher curl', 'barbell', 3, '10-12'],
            ['كيرل بار مكسور (EZ)', 'EZ-bar curl', 'barbell', 3, '10-12'],
        ]],
        'triceps' => ['ذراع خلفي (تراي)', 'Triceps', 'strength', [
            ['متوازي للتراي', 'Triceps dips', 'bodyweight', 3, '8-12'],
            ['ضغط ضيق', 'Close-grip bench press', 'barbell', 3, '8-10'],
            ['بوش داون كابل', 'Triceps pushdown', 'cable', 3, '12-15'],
            ['بوش داون حبل', 'Rope pushdown', 'cable', 3, '12-15'],
            ['سكل كراشر', 'Skull crusher', 'barbell', 3, '10-12'],
            ['تراي أوفر هيد دمبل', 'Overhead dumbbell extension', 'dumbbell', 3, '10-12'],
            ['كيك باك', 'Triceps kickback', 'dumbbell', 3, '12-15'],
            ['ضغط الماس', 'Diamond push-up', 'bodyweight', 3, '10-15'],
        ]],
        'forearms' => ['ساعد', 'Forearms', 'strength', [
            ['كيرل رسغ', 'Wrist curl', 'barbell', 3, '15-20'],
            ['كيرل رسغ عكسي', 'Reverse wrist curl', 'barbell', 3, '15-20'],
            ['كيرل عكسي', 'Reverse curl', 'barbell', 3, '12'],
            ['مشي المزارع', "Farmer's walk", 'dumbbell', 3, '30 ثانية'],
        ]],
        'quads' => ['أرجل أمامية (كوادز)', 'Quadriceps', 'strength', [
            ['سكوات بار', 'Barbell back squat', 'barbell', 4, '6-10'],
            ['سكوات أمامي', 'Front squat', 'barbell', 3, '8'],
            ['ليج برس', 'Leg press', 'machine', 4, '10-12'],
            ['هاك سكوات', 'Hack squat', 'machine', 3, '10-12'],
            ['رفرفة أرجل أمامي', 'Leg extension', 'machine', 3, '12-15'],
            ['لانجز', 'Walking lunges', 'dumbbell', 3, '12'],
            ['سكوات بلغاري', 'Bulgarian split squat', 'dumbbell', 3, '10'],
            ['سكوات جوبلت', 'Goblet squat', 'kettlebell', 3, '12'],
            ['سكوات بوزن الجسم', 'Bodyweight squat', 'bodyweight', 3, '15-20'],
            ['ستيب أب', 'Step-up', 'dumbbell', 3, '10'],
        ]],
        'hamstrings' => ['أرجل خلفية (هامسترنج)', 'Hamstrings', 'strength', [
            ['رومانيان ديدلفت', 'Romanian deadlift', 'barbell', 4, '8-10'],
            ['ثني أرجل نائم', 'Lying leg curl', 'machine', 3, '12'],
            ['ثني أرجل جالس', 'Seated leg curl', 'machine', 3, '12'],
            ['جود مورنينج', 'Good morning', 'barbell', 3, '10'],
            ['ديدلفت برجل واحدة', 'Single-leg Romanian deadlift', 'dumbbell', 3, '10'],
            ['نوردك كيرل', 'Nordic hamstring curl', 'bodyweight', 3, '6-8'],
        ]],
        'glutes' => ['مؤخرة', 'Glutes', 'strength', [
            ['هيب ثراست', 'Hip thrust', 'barbell', 4, '10-12'],
            ['جلوت بريدج', 'Glute bridge', 'bodyweight', 3, '15'],
            ['ركلة خلفية كابل', 'Cable kickback', 'cable', 3, '12-15'],
            ['سكوات سومو', 'Sumo squat', 'dumbbell', 3, '12'],
            ['رفع الأرجل جانباً (مطاط)', 'Banded lateral walk', 'band', 3, '15 خطوة'],
            ['جهاز تبعيد الأرجل', 'Hip abduction machine', 'machine', 3, '15'],
        ]],
        'calves' => ['سمانة', 'Calves', 'strength', [
            ['رفع سمانة واقف', 'Standing calf raise', 'machine', 4, '15-20'],
            ['رفع سمانة جالس', 'Seated calf raise', 'machine', 4, '15-20'],
            ['رفع سمانة على الدرج', 'Step calf raise', 'bodyweight', 3, '20'],
            ['رفع سمانة ليج برس', 'Leg-press calf raise', 'machine', 3, '15-20'],
        ]],
        'abs' => ['بطن', 'Abs & core', 'strength', [
            ['كرانش', 'Crunch', 'bodyweight', 3, '15-20'],
            ['رفع الأرجل', 'Lying leg raise', 'bodyweight', 3, '12-15'],
            ['رفع الأرجل معلّق', 'Hanging leg raise', 'bodyweight', 3, '10-12'],
            ['بلانك', 'Plank', 'bodyweight', 3, '30-60 ثانية'],
            ['بلانك جانبي', 'Side plank', 'bodyweight', 3, '30 ثانية'],
            ['تسلق الجبل', 'Mountain climber', 'bodyweight', 3, '30 ثانية'],
            ['دراجة هوائية للبطن', 'Bicycle crunch', 'bodyweight', 3, '20'],
            ['كرانش كابل', 'Cable crunch', 'cable', 3, '12-15'],
            ['روسيان تويست', 'Russian twist', 'bodyweight', 3, '20'],
            ['عجلة البطن', 'Ab wheel rollout', 'other', 3, '8-12'],
        ]],
        'cardio' => ['كارديو', 'Cardio', 'cardio', [
            ['مشي على السير', 'Treadmill walk', 'machine', 1, '20-40 دقيقة'],
            ['جري على السير', 'Treadmill run', 'machine', 1, '15-30 دقيقة'],
            ['جري في الخارج', 'Outdoor run', 'bodyweight', 1, '20-40 دقيقة'],
            ['دراجة ثابتة', 'Stationary bike', 'machine', 1, '20-40 دقيقة'],
            ['جهاز الإليبتيكال', 'Elliptical', 'machine', 1, '20-30 دقيقة'],
            ['جهاز التجديف', 'Rowing machine', 'machine', 1, '15-20 دقيقة'],
            ['نط الحبل', 'Jump rope', 'other', 4, '60 ثانية'],
            ['صعود الدرج', 'Stair climber', 'machine', 1, '15-20 دقيقة'],
            ['سباحة', 'Swimming', 'bodyweight', 1, '20-40 دقيقة'],
            ['هيت (فترات عالية الشدة)', 'HIIT intervals', 'bodyweight', 8, '30 ثانية / 30 راحة'],
            ['ملاكمة على الكيس', 'Heavy bag boxing', 'other', 5, '3 دقائق'],
        ]],
        'flexibility' => ['مرونة وإطالة', 'Stretching & mobility', 'flexibility', [
            ['إطالة الفخذ الأمامي', 'Quad stretch', 'bodyweight', 2, '30 ثانية'],
            ['إطالة الفخذ الخلفي', 'Hamstring stretch', 'bodyweight', 2, '30 ثانية'],
            ['إطالة الصدر', 'Chest stretch', 'bodyweight', 2, '30 ثانية'],
            ['إطالة الكتف', 'Shoulder stretch', 'bodyweight', 2, '30 ثانية'],
            ['إطالة أسفل الظهر (وضع الطفل)', "Child's pose", 'bodyweight', 2, '45 ثانية'],
            ['القطة والجمل', 'Cat-cow', 'bodyweight', 2, '10'],
            ['إطالة الورك', 'Hip flexor stretch', 'bodyweight', 2, '30 ثانية'],
            ['إطالة السمانة', 'Calf stretch', 'bodyweight', 2, '30 ثانية'],
            ['فوم رولر للظهر', 'Foam roll upper back', 'other', 1, '60 ثانية'],
        ]],
        'functional' => ['وظيفي وكروس فيت', 'Functional & CrossFit', 'functional', [
            ['بيربي', 'Burpee', 'bodyweight', 4, '10-15'],
            ['كيتل بيل سوينج', 'Kettlebell swing', 'kettlebell', 4, '15-20'],
            ['كلين وبرس', 'Clean and press', 'barbell', 4, '5-8'],
            ['ثروستر', 'Thruster', 'barbell', 4, '10'],
            ['ضرب الكرة الطبية', 'Wall ball', 'other', 4, '15'],
            ['قفز الصندوق', 'Box jump', 'bodyweight', 4, '8-10'],
            ['سحب الحبال', 'Battle ropes', 'other', 4, '30 ثانية'],
            ['دفع الزلاجة', 'Sled push', 'other', 4, '20 متر'],
            ['تيرك جيت أب', 'Turkish get-up', 'kettlebell', 3, '4 لكل جانب'],
        ]],
        'warmup' => ['إحماء', 'Warm-up', 'warmup', [
            ['دوران الذراعين', 'Arm circles', 'bodyweight', 2, '15'],
            ['ركض في المكان', 'Jog in place', 'bodyweight', 1, '2 دقيقة'],
            ['قفز جاك', 'Jumping jacks', 'bodyweight', 2, '30'],
            ['ركبة عالية', 'High knees', 'bodyweight', 2, '30 ثانية'],
            ['دوران الورك', 'Hip circles', 'bodyweight', 2, '10 لكل جانب'],
            ['تدفئة الكتف بالمطاط', 'Band pull-apart', 'band', 2, '15'],
            ['سكوات هوائي', 'Air squat', 'bodyweight', 2, '15'],
        ]],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::SECTIONS as [$ar, $en, $kind, $rows]) {
            $category = ExerciseCategory::firstOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'sort_order' => $order],
            );
            $order++;

            foreach ($rows as $i => [$nameAr, $nameEn, $equipment, $sets, $reps]) {
                LibraryExercise::firstOrCreate(
                    ['exercise_category_id' => $category->id, 'name_ar' => $nameAr],
                    [
                        'name_en' => $nameEn,
                        'kind' => $kind,
                        'equipment' => $equipment,
                        'default_sets' => $sets,
                        'default_reps' => $reps,
                        'sort_order' => $i,
                    ],
                );
            }
        }
    }
}
