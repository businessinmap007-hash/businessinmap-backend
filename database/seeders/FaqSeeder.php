<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question_ar' => 'كيف يمكنني إنشاء حساب؟',
                'question_en' => 'How can I create an account?',
                'answer_ar'   => 'يمكنك إنشاء حساب جديد من خلال صفحة التسجيل وإدخال بياناتك.',
                'answer_en'   => 'You can create a new account from the registration page by entering your details.',
            ],
            [
                'question_ar' => 'هل التطبيق مجاني؟',
                'question_en' => 'Is the application free?',
                'answer_ar'   => 'نعم، التطبيق مجاني مع بعض الميزات المدفوعة.',
                'answer_en'   => 'Yes, the application is free with some paid features.',
            ],
            [
                'question_ar' => 'كيف أتواصل مع الدعم الفني؟',
                'question_en' => 'How can I contact technical support?',
                'answer_ar'   => 'يمكنك التواصل مع الدعم الفني من خلال صفحة التواصل داخل التطبيق.',
                'answer_en'   => 'You can contact technical support through the contact page inside the application.',
            ],
            [
                'question_ar' => 'هل يمكن تعديل البيانات الشخصية؟',
                'question_en' => 'Can I edit my personal information?',
                'answer_ar'   => 'نعم، يمكنك تعديل بياناتك الشخصية من صفحة الملف الشخصي.',
                'answer_en'   => 'Yes, you can edit your personal information from the profile page.',
            ],
            [
                'question_ar' => 'ما الفرق بين حساب المستخدم وحساب البزنس؟',
                'question_en' => 'What is the difference between a user account and a business account?',
                'answer_ar'   => 'حساب المستخدم مجاني، ويتيح له تصفح الخدمات والمنتجات والتواصل مباشرة مع مقدمي الخدمات أو البائعين. حساب البزنس يخضع لرسوم على الخدمات التي تتم بالفعل، وبعض أنواع حسابات البزنس يكون لها اشتراك سنوي.',
                'answer_en'   => 'The user account is free and allows browsing services and products and communicating directly with service providers or sellers. The business account is subject to fees based on completed services, and some business accounts require an annual subscription.',
            ],
            [
                'question_ar' => 'ما هي ميزة التقييمات في التطبيق؟',
                'question_en' => 'What is the benefit of the rating system in the application?',
                'answer_ar'   => 'بالنسبة للمستخدم: تكون التقييمات مدفوعة من حساب المستخدم مقابل كل معاملة ناجحة، ويتم منحها عندما يكون مقدم الخدمة راضيًا عن التعامل مع المستخدم، وتُعد بمثابة تقييم لسلوك العميل والتزامه، مما يشجع التجار على التعامل معه بثقة. بالنسبة للبزنس: تكون التقييمات مدفوعة مقابل كل عملية تقييم، وتشمل الالتزام بالمواعيد، وجودة المنتج أو الخدمة، وعدد العمليات الناجحة، ورضا العملاء، مما يضمن للمستخدم أن مقدم الخدمة موثوق وذو خبرة.',
                'answer_en'   => 'For users: ratings are paid from the user’s account for each successful transaction when the service provider is satisfied with the interaction. These ratings reflect the user’s reliability and good conduct, encouraging businesses to deal with them confidently. For businesses: ratings are paid for each evaluation and include punctuality, service or product quality, number of successful transactions, and customer satisfaction, ensuring users that the service provider is experienced and trustworthy.',
            ],

        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                [
                    'question_ar' => $faq['question_ar'],
                ],
                $faq
            );
        }
    }
}
