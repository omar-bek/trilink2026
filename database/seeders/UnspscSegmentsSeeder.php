<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Seeds the top-level UNSPSC segments — Phase 1 / task 1.9.
 *
 * Why only segments and not the full ~150K commodity list?
 *   1. The full UNSPSC dataset is licensed and lives outside the repo.
 *      Production deploys should run a dedicated importer that pulls
 *      from a vendor or local CSV.
 *   2. The 57 segments are stable, public, and enough to get the
 *      hierarchical browser working immediately. Family / class /
 *      commodity rows can be added incrementally as the platform grows.
 *
 * The seeder is idempotent: it `firstOrCreate`s by `unspsc_code` so
 * re-running it does NOT duplicate rows.
 */
class UnspscSegmentsSeeder extends Seeder
{
    /**
     * @return array<int, array{code:int, en:string, ar:string}>
     */
    private function segments(): array
    {
        return [
            ['code' => 10, 'en' => 'Live Plant and Animal Material and Accessories and Supplies', 'ar' => 'النباتات والحيوانات الحية والمواد والملحقات والمستلزمات'],
            ['code' => 11, 'en' => 'Mineral and Textile and Inedible Plant and Animal Materials',  'ar' => 'المعادن والمنسوجات والمواد النباتية والحيوانية غير الصالحة للأكل'],
            ['code' => 12, 'en' => 'Chemicals including Bio Chemicals and Gas Materials',          'ar' => 'الكيماويات بما فيها المواد الكيميائية الحيوية والغازية'],
            ['code' => 13, 'en' => 'Resin and Rosin and Rubber and Foam and Film and Elastomeric Materials', 'ar' => 'الراتنجات والمطاط والإسفنج والأفلام والمواد المرنة'],
            ['code' => 14, 'en' => 'Paper Materials and Products',                                  'ar' => 'مواد ومنتجات الورق'],
            ['code' => 15, 'en' => 'Fuels and Fuel Additives and Lubricants and Anti corrosive Materials', 'ar' => 'الوقود والإضافات والزيوت ومواد منع التآكل'],
            ['code' => 20, 'en' => 'Mining and Well Drilling Machinery and Accessories',            'ar' => 'معدات التعدين وحفر الآبار وملحقاتها'],
            ['code' => 21, 'en' => 'Farming and Fishing and Forestry and Wildlife Machinery',       'ar' => 'معدات الزراعة والصيد والغابات والحياة البرية'],
            ['code' => 22, 'en' => 'Building and Construction Machinery and Accessories',           'ar' => 'معدات وآلات البناء والتشييد'],
            ['code' => 23, 'en' => 'Industrial Manufacturing and Processing Machinery',              'ar' => 'آلات التصنيع والمعالجة الصناعية'],
            ['code' => 24, 'en' => 'Material Handling and Conditioning and Storage Machinery',      'ar' => 'معدات المناولة والتكييف والتخزين'],
            ['code' => 25, 'en' => 'Commercial and Military and Private Vehicles',                  'ar' => 'المركبات التجارية والعسكرية والخاصة'],
            ['code' => 26, 'en' => 'Power Generation and Distribution Machinery and Accessories',   'ar' => 'معدات توليد وتوزيع الطاقة'],
            ['code' => 27, 'en' => 'Tools and General Machinery',                                    'ar' => 'الأدوات والآلات العامة'],
            ['code' => 30, 'en' => 'Structures and Building and Construction and Manufacturing Components', 'ar' => 'مكونات الهياكل والبناء والتصنيع'],
            ['code' => 31, 'en' => 'Manufacturing Components and Supplies',                          'ar' => 'مكونات ومستلزمات التصنيع'],
            ['code' => 32, 'en' => 'Electronic Components and Supplies',                             'ar' => 'المكونات والمستلزمات الإلكترونية'],
            ['code' => 39, 'en' => 'Electrical Systems and Lighting and Components and Accessories', 'ar' => 'الأنظمة الكهربائية والإضاءة وملحقاتها'],
            ['code' => 40, 'en' => 'Distribution and Conditioning Systems and Equipment',            'ar' => 'أنظمة التوزيع والتكييف ومعداتها'],
            ['code' => 41, 'en' => 'Laboratory and Measuring and Observing and Testing Equipment',  'ar' => 'معدات المختبرات والقياس والمراقبة والاختبار'],
            ['code' => 42, 'en' => 'Medical Equipment and Accessories and Supplies',                'ar' => 'المعدات الطبية والملحقات والمستلزمات'],
            ['code' => 43, 'en' => 'Information Technology Broadcasting and Telecommunications',    'ar' => 'تقنية المعلومات والبث والاتصالات'],
            ['code' => 44, 'en' => 'Office Equipment and Accessories and Supplies',                 'ar' => 'معدات وملحقات ومستلزمات المكاتب'],
            ['code' => 45, 'en' => 'Printing and Photographic and Audio and Visual Equipment',      'ar' => 'معدات الطباعة والتصوير والصوتيات والمرئيات'],
            ['code' => 46, 'en' => 'Defense and Law Enforcement and Security and Safety Equipment', 'ar' => 'معدات الدفاع والأمن والسلامة'],
            ['code' => 47, 'en' => 'Cleaning Equipment and Supplies',                                'ar' => 'معدات ومستلزمات النظافة'],
            ['code' => 48, 'en' => 'Service Industry Machinery and Equipment and Supplies',         'ar' => 'آلات ومعدات ومستلزمات قطاع الخدمات'],
            ['code' => 49, 'en' => 'Sports and Recreational Equipment and Supplies and Accessories', 'ar' => 'المعدات والمستلزمات الرياضية والترفيهية'],
            ['code' => 50, 'en' => 'Food Beverage and Tobacco Products',                             'ar' => 'منتجات الأغذية والمشروبات والتبغ'],
            ['code' => 51, 'en' => 'Drugs and Pharmaceutical Products',                              'ar' => 'الأدوية والمنتجات الصيدلانية'],
            ['code' => 52, 'en' => 'Domestic Appliances and Supplies and Consumer Electronic Products', 'ar' => 'الأجهزة المنزلية والمستلزمات والإلكترونيات الاستهلاكية'],
            ['code' => 53, 'en' => 'Apparel and Luggage and Personal Care Products',                'ar' => 'الملابس والأمتعة ومنتجات العناية الشخصية'],
            ['code' => 54, 'en' => 'Timepieces and Jewelry and Gemstone Products',                  'ar' => 'الساعات والمجوهرات والأحجار الكريمة'],
            ['code' => 55, 'en' => 'Published Products',                                              'ar' => 'المنتجات المنشورة'],
            ['code' => 56, 'en' => 'Furniture and Furnishings',                                      'ar' => 'الأثاث والتجهيزات'],
            ['code' => 60, 'en' => 'Musical Instruments and Games and Toys and Arts and Crafts',    'ar' => 'الآلات الموسيقية والألعاب والفنون والحرف'],
            ['code' => 70, 'en' => 'Farming and Fishing and Forestry and Wildlife Contracting Services', 'ar' => 'خدمات الزراعة والصيد والغابات'],
            ['code' => 71, 'en' => 'Mining and oil and gas services',                                'ar' => 'خدمات التعدين والنفط والغاز'],
            ['code' => 72, 'en' => 'Building and Facility Construction and Maintenance Services',   'ar' => 'خدمات البناء والصيانة والمرافق'],
            ['code' => 73, 'en' => 'Industrial Production and Manufacturing Services',              'ar' => 'خدمات الإنتاج والتصنيع الصناعي'],
            ['code' => 76, 'en' => 'Industrial Cleaning Services',                                  'ar' => 'خدمات التنظيف الصناعي'],
            ['code' => 77, 'en' => 'Environmental Services',                                         'ar' => 'الخدمات البيئية'],
            ['code' => 78, 'en' => 'Transportation and Storage and Mail Services',                  'ar' => 'خدمات النقل والتخزين والبريد'],
            ['code' => 80, 'en' => 'Management and Business Professionals and Administrative Services', 'ar' => 'الخدمات الإدارية والمهنية والاستشارية'],
            ['code' => 81, 'en' => 'Engineering and Research and Technology Based Services',         'ar' => 'الخدمات الهندسية والبحثية والتقنية'],
            ['code' => 82, 'en' => 'Editorial and Design and Graphic and Fine Art Services',         'ar' => 'خدمات التصميم والجرافيك والفنون'],
            ['code' => 83, 'en' => 'Public Utilities and Public Sector Related Services',           'ar' => 'خدمات المرافق العامة والقطاع العام'],
            ['code' => 84, 'en' => 'Financial and Insurance Services',                                'ar' => 'الخدمات المالية والتأمينية'],
            ['code' => 85, 'en' => 'Healthcare Services',                                             'ar' => 'خدمات الرعاية الصحية'],
            ['code' => 86, 'en' => 'Education and Training Services',                                 'ar' => 'خدمات التعليم والتدريب'],
            ['code' => 90, 'en' => 'Travel and Food and Lodging and Entertainment Services',         'ar' => 'خدمات السفر والمطاعم والإقامة والترفيه'],
            ['code' => 91, 'en' => 'Personal and Domestic Services',                                  'ar' => 'الخدمات الشخصية والمنزلية'],
            ['code' => 92, 'en' => 'National Defense and Public Order and Security and Safety Services', 'ar' => 'خدمات الدفاع الوطني والأمن العام والسلامة'],
            ['code' => 93, 'en' => 'Politics and Civic Affairs Services',                             'ar' => 'الخدمات السياسية والشؤون المدنية'],
            ['code' => 94, 'en' => 'Organizations and Clubs',                                         'ar' => 'المنظمات والنوادي'],
            ['code' => 95, 'en' => 'Land and Buildings and Structures and Thoroughfares',            'ar' => 'الأراضي والمباني والهياكل والطرق'],
        ];
    }

    public function run(): void
    {
        foreach ($this->segments() as $row) {
            $code = (int) $row['code'];
            $unspsc8 = sprintf('%02d000000', $code);

            Category::firstOrCreate(
                ['unspsc_code' => $unspsc8],
                [
                    'name' => $row['en'],
                    'name_ar' => $row['ar'],
                    'parent_id' => null,
                    'is_active' => true,
                    'unspsc_segment' => $code,
                    'unspsc_family' => null,
                    'unspsc_class' => null,
                    'unspsc_commodity' => null,
                ]
            );
        }
    }
}
