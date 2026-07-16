<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use App\Services\AI\IntentDetectionService;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Seed the knowledge_base table with bilingual (English + Tagalog) rice-farming,
     * climate, ML, and system content. One row per language per topic, since the
     * knowledge_base table has no language column and KnowledgeBaseService::search()
     * returns the answer verbatim.
     */
    public function run(): void
    {
        foreach ($this->topics() as $topic) {
            foreach (['en', 'tl'] as $lang) {
                KnowledgeBase::query()->updateOrCreate(
                    ['question' => $topic[$lang]['q'], 'category' => $topic['category']],
                    [
                        'answer' => $topic[$lang]['a'],
                        'keywords' => $topic[$lang]['k'],
                        'source_type' => 'Knowledge Base',
                        'source_name' => $topic['source_name'] ?? 'iClimate Knowledge Base',
                        'source_url' => null,
                        'verified' => true,
                        'times_used' => 0,
                        'confidence' => $topic['confidence'] ?? 88,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array{category: string, confidence?: int, source_name?: string, en: array{q: string, a: string, k: array<int, string>}, tl: array{q: string, a: string, k: array<int, string>}}>
     */
    private function topics(): array
    {
        return [
            ...$this->generalAgricultureTopics(),
            ...$this->farmingAdvisoryTopics(),
            ...$this->systemHelpTopics(),
            ...$this->barangayInfoTopics(),
            ...$this->maoReportTopics(),
            ...$this->itSystemStatusTopics(),
        ];
    }

    private function generalAgricultureTopics(): array
    {
        $category = IntentDetectionService::GENERAL_AGRICULTURE;

        return [
            [
                'category' => $category, 'confidence' => 88,
                'en' => ['q' => 'What is rice farming?', 'a' => 'Rice farming is growing palay (rice) through land preparation, planting, water and nutrient management, pest control, and harvesting. In the Philippines it is grown mainly in lowland irrigated or rainfed fields, timed around the wet and dry seasons.', 'k' => ['rice', 'farming', 'palay', 'basics', 'agriculture']],
                'tl' => ['q' => 'Ano ang pagsasaka ng palay?', 'a' => 'Ang pagsasaka ng palay ay ang pagtatanim ng palay sa pamamagitan ng paghahanda ng lupa, pagtatanim, pamamahala ng tubig at abono, pagkontrol ng peste, at pag-aani. Sa Pilipinas, karaniwang itinatanim ito sa lowland irrigated o rainfed na bukid, ayon sa tag-ulan at tag-init.', 'k' => ['palay', 'pagsasaka', 'sakahan', 'basics']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What is the germination stage of rice?', 'a' => 'Germination is the first growth stage: the rice seed absorbs water and the radicle and coleoptile emerge, usually within 5-7 days after sowing under good conditions.', 'k' => ['germination', 'growth stage', 'seed', 'rice']],
                'tl' => ['q' => 'Ano ang germination stage ng palay?', 'a' => 'Ang germination ang unang growth stage: sumisipsip ng tubig ang binhi at lumalabas ang ugat at unang dahon, karaniwang 5-7 araw pagkatapos itanim kung maganda ang kondisyon.', 'k' => ['germination', 'growth stage', 'binhi', 'palay']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What happens in the seedling stage of rice?', 'a' => 'The seedling stage covers the first 2-3 weeks after emergence, when the first true leaves develop. Seedlings are usually ready for transplanting around 18-25 days after sowing.', 'k' => ['seedling', 'growth stage', 'transplanting', 'rice']],
                'tl' => ['q' => 'Ano ang nangyayari sa seedling stage ng palay?', 'a' => 'Ang seedling stage ay sumasaklaw sa unang 2-3 linggo pagkasibol, kung saan lumalabas ang unang tunay na dahon. Karaniwang handa na ang punla para i-transplant sa 18-25 araw pagkatanim.', 'k' => ['seedling', 'punla', 'transplant', 'palay']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What happens during the tillering or vegetative stage?', 'a' => 'During tillering, the rice plant grows extra shoots (tillers) from its base. This is the main phase that builds yield potential and typically lasts until panicle initiation, around 30-60 days depending on the variety.', 'k' => ['tillering', 'vegetative', 'growth stage', 'tillers', 'rice']],
                'tl' => ['q' => 'Ano ang nangyayari sa tillering o vegetative stage?', 'a' => 'Sa tillering stage, lumalabas ang karagdagang sanga (tillers) mula sa ugat ng palay. Ito ang pangunahing yugto na bumubuo ng potensyal na ani, karaniwang tumatagal hanggang sa panicle initiation, mga 30-60 araw depende sa variety.', 'k' => ['tillering', 'vegetative', 'palay', 'sanga']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What happens during the reproductive stage (booting, heading, flowering)?', 'a' => 'The panicle develops inside the stem (booting), emerges (heading), then flowers and pollinates (flowering). This is the most critical stage for yield -- it is very sensitive to water stress, extreme heat, and pest damage.', 'k' => ['booting', 'heading', 'flowering', 'reproductive stage', 'growth stage', 'rice']],
                'tl' => ['q' => 'Ano ang nangyayari sa reproductive stage (booting, heading, flowering)?', 'a' => 'Nabubuo ang panicle sa loob ng tangkay (booting), lumalabas ito (heading), pagkatapos namumulaklak at nagpo-pollinate (flowering). Ito ang pinaka-kritikal na yugto para sa ani -- sensitibo ito sa kakulangan ng tubig, sobrang init, at peste.', 'k' => ['booting', 'heading', 'flowering', 'palay', 'reproductive']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What happens during the ripening or maturity stage?', 'a' => 'Grains fill and mature through the milk, dough, and mature grain stages, usually about 30 days after flowering. The crop is ready to harvest once around 80-85% of grains turn golden yellow.', 'k' => ['ripening', 'maturity', 'growth stage', 'harvest', 'rice']],
                'tl' => ['q' => 'Ano ang nangyayari sa ripening o maturity stage?', 'a' => 'Pinupuno at humihinog ang butil sa milk, dough, at mature grain stage, karaniwang 30 araw pagkatapos mamulaklak. Handa nang anihin kapag 80-85% ng mga butil ay naging ginintuang dilaw na.', 'k' => ['ripening', 'maturity', 'ani', 'palay']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What is the difference between inbred and hybrid rice varieties?', 'a' => 'Inbred varieties (like many NSIC Rc lines) are open-pollinated, so farmers can save and reuse seeds. Hybrid varieties usually have higher yield potential but seed must be repurchased every season because saved hybrid seed does not breed true.', 'k' => ['inbred', 'hybrid', 'variety', 'nsic rc', 'seeds']],
                'tl' => ['q' => 'Ano ang pagkakaiba ng inbred at hybrid na variety ng palay?', 'a' => 'Ang inbred varieties (gaya ng maraming NSIC Rc) ay open-pollinated, kaya puwedeng itago at gamitin muli ang binhi. Ang hybrid varieties ay mas mataas ang potensyal na ani pero kailangang bumili ulit ng binhi kada season dahil hindi consistent ang resulta ng na-save na hybrid seed.', 'k' => ['inbred', 'hybrid', 'variety', 'binhi']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How do I choose a rice variety for the season?', 'a' => 'Match the variety maturity duration to the length of the wet or dry season available, consider local pest and disease resistance needs (e.g. Tungro or blast-resistant lines), and factor in market or milling preference.', 'k' => ['rice variety', 'choosing variety', 'season', 'maturity duration']],
                'tl' => ['q' => 'Paano pumili ng variety ng palay para sa season?', 'a' => 'Itugma ang maturity duration ng variety sa haba ng tag-ulan o tag-init, isaalang-alang ang kailangang resistensya sa peste at sakit (hal. Tungro o blast-resistant), at ang gusto ng merkado o milling.', 'k' => ['variety', 'palay', 'season', 'pagpili']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is the difference between traditional and modern rice varieties?', 'a' => 'Traditional varieties are often taller and lower-yielding but more resilient and locally adapted. Modern varieties bred by PhilRice or IRRI are selected for higher yield and specific pest/disease resistance.', 'k' => ['traditional variety', 'modern variety', 'philrice', 'irri']],
                'tl' => ['q' => 'Ano ang pagkakaiba ng traditional at modern na variety ng palay?', 'a' => 'Ang traditional varieties ay karaniwang mas matangkad at mas mababa ang ani pero mas matatag at angkop sa lokal na kondisyon. Ang modern varieties mula sa PhilRice o IRRI ay pinili para sa mas mataas na ani at partikular na resistensya sa peste/sakit.', 'k' => ['traditional', 'modern', 'variety', 'philrice']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What soil type is best for rice farming?', 'a' => 'Clay and clay-loam soils are ideal for lowland rice because they retain water well. Sandy soils drain too fast and need careful, more frequent water management to keep the field flooded.', 'k' => ['soil type', 'clay soil', 'sandy soil', 'best soil for rice']],
                'tl' => ['q' => 'Anong soil type ang pinakamainam para sa palay?', 'a' => 'Ang clay at clay-loam na lupa ang pinakaangkop para sa lowland rice dahil mahusay itong humahawak ng tubig. Ang sandy soil naman ay mabilis mag-drain kaya kailangan ng mas maingat at madalas na pamamahala ng tubig.', 'k' => ['soil', 'lupa', 'clay', 'sandy']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What soil pH is best for rice?', 'a' => 'Rice grows best in slightly acidic to neutral soil, around pH 5.5-6.5. Very acidic or very alkaline soil should be corrected with lime or gypsum based on a soil test.', 'k' => ['soil ph', 'ph level', 'soil acidity', 'lime']],
                'tl' => ['q' => 'Ano ang pinakamainam na soil pH para sa palay?', 'a' => 'Mainam sa palay ang bahagyang acidic hanggang neutral na lupa, mga pH 5.5-6.5. Kung sobrang acidic o alkaline ang lupa, itama ito gamit ang apog (lime) o gypsum batay sa soil test.', 'k' => ['ph', 'lupa', 'acidic', 'apog']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How can I improve soil fertility?', 'a' => 'Add compost, vermicompost, or well-decomposed animal manure to build organic matter and support microbial activity. Combine organic inputs with recommended inorganic fertilizer for the best balance of long-term soil health and yield.', 'k' => ['soil fertility', 'organic matter', 'compost', 'improve soil']],
                'tl' => ['q' => 'Paano mapapabuti ang fertility ng lupa?', 'a' => 'Magdagdag ng compost, vermicompost, o tunay na nabulok na dumi ng hayop para bumuo ng organic matter at suportahan ang microbial activity. Pagsamahin ang organic inputs at inorganic fertilizer para sa mas mahusay na balanse ng soil health at ani.', 'k' => ['fertility', 'lupa', 'compost', 'organic']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'Why is water retention important for rice soil?', 'a' => 'Good water retention (built by puddling wet soil during land preparation) reduces seepage and percolation loss, which is critical for keeping a lowland flooded rice field properly submerged with less water waste.', 'k' => ['water retention', 'soil moisture', 'puddling', 'lowland rice']],
                'tl' => ['q' => 'Bakit mahalaga ang water retention ng lupa para sa palay?', 'a' => 'Ang mahusay na water retention (sa pamamagitan ng puddling ng basang lupa habang naghahanda ng lupa) ay nagpapababa ng pagtagas ng tubig, mahalaga ito para mapanatiling nakababad nang maayos ang lowland rice field nang hindi nag-aaksaya ng tubig.', 'k' => ['water retention', 'soil moisture', 'puddling', 'tubig']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How does climate change affect rice farming?', 'a' => 'Climate change is shifting rainfall patterns, intensifying typhoons, and raising temperatures, which can stress flowering and reduce yield. Farmers increasingly need to adapt planting schedules and choose more resilient varieties.', 'k' => ['climate change', 'rice farming', 'impact', 'weather pattern']],
                'tl' => ['q' => 'Paano naaapektuhan ng climate change ang pagsasaka ng palay?', 'a' => 'Binabago ng climate change ang pattern ng ulan, pinalalakas ang bagyo, at pinapataas ang temperatura, na nakaka-stress sa pagbubulaklak at nagpapababa ng ani. Kailangang i-adjust ng mga magsasaka ang planting schedule at pumili ng mas matatag na variety.', 'k' => ['climate change', 'palay', 'epekto', 'panahon']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How does El Niño affect rice farming?', 'a' => 'El Niño typically brings drier-than-normal conditions and a delayed wet season onset, raising drought risk. Farmers may need to adjust planting dates, rely more on irrigation, or choose drought-tolerant varieties.', 'k' => ['el nino', 'el niño', 'drought', 'dry season', 'rice']],
                'tl' => ['q' => 'Paano naaapektuhan ng El Niño ang pagsasaka ng palay?', 'a' => 'Karaniwang nagdudulot ang El Niño ng mas tuyong kondisyon at naantalang pagsisimula ng tag-ulan, kaya mas mataas ang panganib ng tagtuyot. Maaaring kailanganing i-adjust ang petsa ng pagtatanim, umasa sa irigasyon, o pumili ng drought-tolerant na variety.', 'k' => ['el nino', 'tagtuyot', 'palay', 'irigasyon']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How does La Niña affect rice farming?', 'a' => 'La Niña typically brings wetter-than-normal conditions with higher flood and typhoon risk. Farmers may need better field drainage, dikes, or flood-tolerant varieties in low-lying barangays.', 'k' => ['la nina', 'la niña', 'flood', 'wet season', 'rice']],
                'tl' => ['q' => 'Paano naaapektuhan ng La Niña ang pagsasaka ng palay?', 'a' => 'Karaniwang nagdudulot ang La Niña ng mas maulan na kondisyon na may mas mataas na panganib ng baha at bagyo. Kailangan ng mas mahusay na drainage, pilapil, o flood-tolerant na variety sa mababang barangay.', 'k' => ['la nina', 'baha', 'palay', 'bagyo']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What are the typical seasonal rainfall and temperature patterns in Lian, Batangas?', 'a' => 'Lian, Batangas typically has a wet season from around June to November (higher rainfall and typhoon exposure) and a dry season from around December to May (lower rainfall, needing irrigation to crop successfully).', 'k' => ['seasonal pattern', 'rainfall trend', 'lian batangas', 'wet season', 'dry season']],
                'tl' => ['q' => 'Ano ang karaniwang seasonal pattern ng ulan at temperatura sa Lian, Batangas?', 'a' => 'Karaniwang may tag-ulan ang Lian, Batangas mula Hunyo hanggang Nobyembre (mas maraming ulan at bagyo) at tag-init mula Disyembre hanggang Mayo (mas kaunting ulan, kailangan ng irigasyon para umani).', 'k' => ['seasonal', 'ulan', 'lian batangas', 'tag-ulan', 'tag-init']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How should I prepare for a typhoon as a rice farmer?', 'a' => 'If the crop is near maturity, consider harvesting early. Reinforce field drainage and dikes, secure farm equipment, and monitor PAGASA bulletins closely as the typhoon approaches.', 'k' => ['typhoon', 'disaster preparedness', 'bagyo preparation', 'paghahanda']],
                'tl' => ['q' => 'Paano dapat maghanda ang magsasaka sa darating na bagyo?', 'a' => 'Kung malapit nang anihin ang pananim, isaalang-alang ang maagang pag-ani. Palakasin ang drainage at pilapil, siguraduhing ligtas ang kagamitan sa bukid, at bantayan ang bulletin ng PAGASA habang papalapit ang bagyo.', 'k' => ['bagyo', 'paghahanda', 'disaster', 'pagbabala']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How should I prepare for flooding?', 'a' => 'Clear drainage canals before the wet season starts, consider flood-tolerant varieties if your barangay is flood-prone, and avoid planting in the lowest-lying parts of the field during peak typhoon months.', 'k' => ['flood', 'flood preparation', 'drainage', 'disaster preparedness']],
                'tl' => ['q' => 'Paano dapat maghanda sa posibleng pagbaha?', 'a' => 'Linisin ang drainage canal bago magsimula ang tag-ulan, isaalang-alang ang flood-tolerant na variety kung madalas mabaha ang barangay mo, at iwasan ang pagtatanim sa pinakamababang bahagi ng bukid tuwing tuktok ng bagyo season.', 'k' => ['baha', 'paghahanda', 'drainage', 'disaster']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How should I prepare for drought?', 'a' => 'Schedule planting to align with reliable rainfall or irrigation onset, consider drought-tolerant varieties, and use water-saving techniques such as Alternate Wetting and Drying (AWD) when water is limited.', 'k' => ['drought', 'drought preparation', 'water saving', 'awd']],
                'tl' => ['q' => 'Paano dapat maghanda sa tagtuyot?', 'a' => 'I-schedule ang pagtatanim ayon sa maaasahang pagsisimula ng ulan o irigasyon, isaalang-alang ang drought-tolerant na variety, at gumamit ng water-saving techniques tulad ng Alternate Wetting and Drying (AWD) kung limitado ang tubig.', 'k' => ['tagtuyot', 'paghahanda', 'tubig', 'awd']],
            ],
            [
                'category' => $category, 'confidence' => 84, 'source_name' => 'Department of Agriculture / RCEF guidance',
                'en' => ['q' => 'What is RCEF, the Rice Competitiveness Enhancement Fund?', 'a' => 'RCEF is a government fund, sourced from rice import tariffs, that provides free certified seeds, farm machinery, credit assistance, and skills training to registered rice farmers to improve productivity and competitiveness.', 'k' => ['rcef', 'rice competitiveness enhancement fund', 'government program', 'da program']],
                'tl' => ['q' => 'Ano ang RCEF o Rice Competitiveness Enhancement Fund?', 'a' => 'Ang RCEF ay pondo ng gobyerno, mula sa taripa ng imported na bigas, na nagbibigay ng libreng certified seeds, makinarya sa sakahan, tulong pang-kredito, at training sa mga rehistradong magsasaka ng palay para mapataas ang produktibidad.', 'k' => ['rcef', 'gobyerno program', 'binhi', 'training']],
            ],
            [
                'category' => $category, 'confidence' => 84, 'source_name' => 'PhilRice guidance',
                'en' => ['q' => 'What services does PhilRice provide to farmers?', 'a' => 'The Philippine Rice Research Institute (PhilRice) develops improved rice varieties, provides technical training, and publishes farming technology recommendations that farmers and MAO staff can apply locally.', 'k' => ['philrice', 'rice research institute', 'government program', 'training']],
                'tl' => ['q' => 'Anong serbisyo ang ibinibigay ng PhilRice sa mga magsasaka?', 'a' => 'Ang Philippine Rice Research Institute (PhilRice) ay bumubuo ng mas mahusay na variety ng palay, nagbibigay ng technical training, at naglalathala ng rekomendasyon sa teknolohiya sa pagsasaka na magagamit ng mga magsasaka at MAO staff.', 'k' => ['philrice', 'rice research', 'training', 'gobyerno']],
            ],
            [
                'category' => $category, 'confidence' => 82, 'source_name' => 'PhilRice PalayCheck guidance',
                'en' => ['q' => 'What is the PalayCheck system?', 'a' => 'PalayCheck is a PhilRice-developed integrated crop management system that gives farmers a checklist of best practices at each growth stage -- from land preparation to harvest -- to help maximize yield and reduce input waste.', 'k' => ['palaycheck', 'crop management', 'philrice', 'best practices']],
                'tl' => ['q' => 'Ano ang PalayCheck system?', 'a' => 'Ang PalayCheck ay isang integrated crop management system mula sa PhilRice na nagbibigay ng checklist ng pinakamahusay na gawain sa bawat growth stage -- mula land preparation hanggang harvest -- para matulungan i-maximize ang ani.', 'k' => ['palaycheck', 'crop management', 'philrice', 'checklist']],
            ],
            [
                'category' => $category, 'confidence' => 80, 'source_name' => 'DA-ATI guidance',
                'en' => ['q' => 'What agriculture training and seed distribution programs are available from the government?', 'a' => 'The Department of Agriculture\'s Agricultural Training Institute (DA-ATI) offers farmer training programs, while DA/MAO periodically distributes certified seeds and fertilizer subsidies -- watch iClimate announcements for local schedules.', 'k' => ['da-ati', 'training', 'seed distribution', 'fertilizer distribution', 'government program']],
                'tl' => ['q' => 'Anong training at seed distribution program ang available mula sa gobyerno?', 'a' => 'Ang Agricultural Training Institute ng Department of Agriculture (DA-ATI) ay nag-aalok ng training para sa magsasaka, habang ang DA/MAO ay pana-panahong namimigay ng certified seeds at fertilizer subsidy -- bantayan ang iClimate announcements para sa lokal na schedule.', 'k' => ['da-ati', 'training', 'binhi', 'gobyerno program']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What is the difference between wet season and dry season planting?', 'a' => 'Wet season crop is typically planted around June-July relying on monsoon rains, while dry season crop is planted around November-December relying on irrigation. Variety choice and pest pressure differ between the two.', 'k' => ['wet season', 'dry season', 'planting season', 'crop calendar']],
                'tl' => ['q' => 'Ano ang pagkakaiba ng pagtatanim sa tag-ulan at tag-init?', 'a' => 'Ang tag-ulan na pananim ay karaniwang itinatanim sa Hunyo-Hulyo, umaasa sa ulan; ang tag-init na pananim naman ay itinatanim sa Nobyembre-Disyembre, umaasa sa irigasyon. Iba rin ang pagpili ng variety at peste sa dalawa.', 'k' => ['tag-ulan', 'tag-init', 'season', 'crop calendar']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What are the steps in land preparation before planting rice?', 'a' => 'Land preparation involves plowing, harrowing, leveling, and (for lowland rice) puddling, usually done 2-3 weeks before transplanting or sowing, to create a weed-free, level, well-puddled field.', 'k' => ['land preparation', 'plowing', 'harrowing', 'puddling']],
                'tl' => ['q' => 'Ano ang mga hakbang sa paghahanda ng lupa bago magtanim ng palay?', 'a' => 'Kasama sa land preparation ang pag-araro, pag-harrow, pag-level, at (para sa lowland rice) pag-puddle, karaniwang 2-3 linggo bago mag-transplant o magsabog, para makagawa ng malinis, pantay, at maayos na bukid.', 'k' => ['land preparation', 'araro', 'puddling', 'paghahanda']],
            ],
        ];
    }

    private function farmingAdvisoryTopics(): array
    {
        $category = IntentDetectionService::FARMING_ADVISORY;

        return [
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What is Urea fertilizer used for?', 'a' => 'Urea (46-0-0) is a fast-acting nitrogen fertilizer, commonly split-applied as basal plus topdressing at tillering and panicle initiation. Excess urea promotes lodging and makes the crop more attractive to pests and disease.', 'k' => ['urea', 'fertilizer', 'nitrogen', '46-0-0']],
                'tl' => ['q' => 'Para saan ang Urea fertilizer?', 'a' => 'Ang Urea (46-0-0) ay mabilis kumilos na nitrogen fertilizer, karaniwang hinahati ang aplikasyon: basal at topdress sa tillering at panicle initiation. Sobrang Urea ay nagdudulot ng lodging at mas nakakaakit ng peste at sakit.', 'k' => ['urea', 'abono', 'nitrogen', 'pataba']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'When should I use complete fertilizer (14-14-14)?', 'a' => 'Complete fertilizer (14-14-14) provides a balanced N-P-K mix and is typically applied basal during land preparation or at transplanting to help establish strong roots and early growth.', 'k' => ['complete fertilizer', '14-14-14', 'npk', 'basal application']],
                'tl' => ['q' => 'Kailan dapat gamitin ang complete fertilizer (14-14-14)?', 'a' => 'Ang complete fertilizer (14-14-14) ay balanseng N-P-K, karaniwang ginagamit bilang basal application sa land preparation o transplanting para makatulong sa matatag na ugat at maagang paglaki.', 'k' => ['complete fertilizer', '14-14-14', 'npk', 'basal']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What are the benefits of organic fertilizer for rice?', 'a' => 'Organic fertilizer (compost, vermicompost, animal manure) improves soil structure and microbial activity and releases nutrients slowly. It supports long-term soil health and is usually combined with inorganic fertilizer for the best yield.', 'k' => ['organic fertilizer', 'compost', 'soil health', 'vermicompost']],
                'tl' => ['q' => 'Ano ang benepisyo ng organic fertilizer sa palay?', 'a' => 'Ang organic fertilizer (compost, vermicompost, dumi ng hayop) ay nagpapabuti ng soil structure at microbial activity at dahan-dahan naglalabas ng sustansya. Suporta ito sa pangmatagalang soil health, karaniwang isinasama sa inorganic fertilizer.', 'k' => ['organic fertilizer', 'compost', 'lupa', 'abono']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What does nitrogen do for rice plants and when should it be applied?', 'a' => 'Nitrogen promotes leaf and tiller growth and is needed most during early vegetative growth and panicle initiation. Excess nitrogen causes lodging and greater pest/disease susceptibility, so apply it in split doses rather than all at once.', 'k' => ['nitrogen', 'fertilizer', 'timing', 'leaf growth']],
                'tl' => ['q' => 'Ano ang gawain ng nitrogen sa palay at kailan ito dapat ilagay?', 'a' => 'Pinapalakas ng nitrogen ang paglaki ng dahon at tiller, kailangan ito lalo na sa maagang vegetative stage at panicid initiation. Ang sobrang nitrogen ay nagdudulot ng lodging at mas madaling atakihin ng peste, kaya mas mainam na hatiin ang aplikasyon.', 'k' => ['nitrogen', 'abono', 'timing', 'dahon']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What does phosphorus do for rice plants?', 'a' => 'Phosphorus promotes root development and early tillering. It is less mobile in soil, so it is normally applied basal (before or at planting) rather than as a topdress later in the season.', 'k' => ['phosphorus', 'fertilizer', 'root development', 'basal']],
                'tl' => ['q' => 'Ano ang gawain ng phosphorus sa palay?', 'a' => 'Pinapalakas ng phosphorus ang paglaki ng ugat at maagang tillering. Hindi ito gaanong gumagalaw sa lupa, kaya karaniwang inilalagay bilang basal application (bago o kasabay ng pagtatanim).', 'k' => ['phosphorus', 'abono', 'ugat', 'basal']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What does potassium do for rice plants?', 'a' => 'Potassium strengthens stems for better lodging resistance and improves grain filling and disease resistance. It is usually applied basal, with an additional split sometimes given at panicle initiation.', 'k' => ['potassium', 'fertilizer', 'lodging resistance', 'grain filling']],
                'tl' => ['q' => 'Ano ang gawain ng potassium sa palay?', 'a' => 'Pinapalakas ng potassium ang tangkay para hindi madaling matumba (lodging resistance) at pinapabuti ang pagpuno ng butil at resistensya sa sakit. Karaniwang basal application ito, minsan may karagdagan sa panicle initiation.', 'k' => ['potassium', 'abono', 'tangkay', 'butil']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'How much fertilizer should I apply per hectare?', 'a' => 'A common general guide for irrigated lowland rice is about 90-120 kg nitrogen, 30-40 kg phosphorus (P2O5), and 30-40 kg potassium (K2O) per hectare, adjusted for soil test results. Always confirm the exact rate with your MAO technician\'s soil-based recommendation.', 'k' => ['fertilizer rate', 'how much fertilizer', 'per hectare', 'npk rate']],
                'tl' => ['q' => 'Gaano karaming fertilizer ang dapat ilagay kada ektarya?', 'a' => 'Karaniwang gabay para sa irrigated lowland rice ay humigit-kumulang 90-120 kg nitrogen, 30-40 kg phosphorus (P2O5), at 30-40 kg potassium (K2O) kada ektarya, aayusin base sa soil test. Kumpirmahin lagi sa inyong MAO technician ang eksaktong rekomendasyon.', 'k' => ['fertilizer rate', 'gaano karami', 'kada ektarya', 'npk']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'When should I apply fertilizer during the season?', 'a' => 'Fertilizer is typically applied in three splits: basal during land preparation, a second split at tillering (about 15-20 days after transplanting), and a third split at panicle initiation (about 35-45 days after transplanting).', 'k' => ['fertilizer schedule', 'when to apply fertilizer', 'basal', 'topdress', 'timing']],
                'tl' => ['q' => 'Kailan dapat maglagay ng fertilizer sa buong season?', 'a' => 'Karaniwang hinahati sa tatlong beses ang paglalagay ng fertilizer: basal sa land preparation, ikalawa sa tillering (mga 15-20 araw pagkatanim), at ikatlo sa panicle initiation (mga 35-45 araw pagkatanim).', 'k' => ['fertilizer schedule', 'kailan maglagay', 'topdress', 'timing']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What is the brown planthopper and how do I control it?', 'a' => 'The brown planthopper (BPH) sucks sap at the base of tillers, causing circular "hopperburn" patches of drying, browning rice. It thrives in dense, over-fertilized (excess nitrogen), humid fields. Control it with resistant varieties, avoiding excess nitrogen, conserving natural enemies like spiders, and synchronized planting.', 'k' => ['brown planthopper', 'bph', 'hopperburn', 'pest', 'rice pest']],
                'tl' => ['q' => 'Ano ang brown planthopper at paano ito kontrolin?', 'a' => 'Ang brown planthopper (BPH) ay sumisipsip ng katas sa ugat ng tiller, nagdudulot ng bilog na "hopperburn" na tuyot at kayumangging bahagi ng palay. Umuunlad ito sa siksik, sobrang-abonong (excess nitrogen), mahalumigmig na bukid. Kontrolin sa pamamagitan ng resistant variety, pag-iwas sa sobrang nitrogen, at pangangalaga sa natural na kaaway nito tulad ng gagamba.', 'k' => ['brown planthopper', 'bph', 'hopperburn', 'peste']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What is stem borer and how do I control it?', 'a' => 'Stem borer larvae bore into the rice stem, causing "deadheart" (a dead central shoot) during the vegetative stage or "whitehead" (an empty, unfilled panicle) during the reproductive stage. Control with field sanitation, synchronized planting, resistant varieties, and light traps.', 'k' => ['stem borer', 'deadheart', 'whitehead', 'pest', 'rice pest']],
                'tl' => ['q' => 'Ano ang stem borer at paano ito kontrolin?', 'a' => 'Ang stem borer larvae ay bumubutas sa tangkay ng palay, sanhi ng "deadheart" (patay na gitnang usbong) sa vegetative stage o "whitehead" (walang laman na uhay) sa reproductive stage. Kontrolin sa pamamagitan ng field sanitation, synchronized planting, resistant variety, at light traps.', 'k' => ['stem borer', 'deadheart', 'whitehead', 'peste']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What is rice bug and how do I control it?', 'a' => 'Rice bug (Leptocorisa oratorius) adults suck developing grains, causing unfilled or discolored "pecky" grains. They are most active during flowering to milk stage, especially early morning and late afternoon. Control with field sanitation, removing grassy alternate hosts, and synchronized planting.', 'k' => ['rice bug', 'leptocorisa', 'pecky rice', 'pest', 'rice pest']],
                'tl' => ['q' => 'Ano ang rice bug at paano ito kontrolin?', 'a' => 'Ang rice bug (Leptocorisa oratorius) ay sumisipsip sa nabubuong butil, sanhi ng walang laman o may batik na butil. Pinaka-aktibo ito sa flowering hanggang milk stage, lalo na umaga at hapon. Kontrolin sa field sanitation, pag-alis ng damo, at synchronized planting.', 'k' => ['rice bug', 'peste', 'butil']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is leaf folder and how do I control it?', 'a' => 'Leaf folder larvae fold leaves and scrape the green tissue, leaving white or transparent streaks. Damage is often cosmetic since rice compensates well; conserve natural enemies like parasitic wasps and avoid excess nitrogen, using insecticide only if damage is severe.', 'k' => ['leaf folder', 'pest', 'rice pest', 'leaf damage']],
                'tl' => ['q' => 'Ano ang leaf folder at paano ito kontrolin?', 'a' => 'Ang leaf folder larvae ay tumutupi ng dahon at kinakayas ang berdeng bahagi, nag-iiwan ng puting guhit. Kadalasan kosmetiko lang ang pinsala; pangalagaan ang natural na kaaway nito at iwasan ang sobrang nitrogen.', 'k' => ['leaf folder', 'peste', 'dahon']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is armyworm and how do I control it?', 'a' => 'Armyworm larvae feed on leaves at night and can strip whole plants during outbreaks, especially after heavy rains. Monitor fields at night, remove weeds and grasses that host them, and rely on biological control before resorting to insecticide.', 'k' => ['armyworm', 'pest', 'rice pest', 'outbreak']],
                'tl' => ['q' => 'Ano ang armyworm at paano ito kontrolin?', 'a' => 'Ang armyworm larvae ay kumakain ng dahon sa gabi at maaaring ubusin ang buong pananim tuwing outbreak, lalo na pagkatapos ng malakas na ulan. Subaybayan ang bukid sa gabi, alisin ang damo, at gamitin ang biological control bago ang pestisidyo.', 'k' => ['armyworm', 'peste', 'gabi']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is rice blast disease?', 'a' => 'Rice blast (caused by Pyricularia oryzae) shows diamond-shaped lesions with a gray center and brown margin on leaves; neck blast can snap the panicle. It is favored by high nitrogen, high humidity, and cool temperatures. Control with resistant varieties, balanced fertilization, and fungicide if severe.', 'k' => ['rice blast', 'disease', 'pyricularia', 'leaf lesion']],
                'tl' => ['q' => 'Ano ang rice blast disease?', 'a' => 'Ang rice blast (dulot ng Pyricularia oryzae) ay may hugis-diamante na sugat na kulay-abo sa gitna at kayumanggi sa gilid sa dahon; ang neck blast ay pwedeng bumali sa uhay. Higit itong lumalala sa sobrang nitrogen at mahalumigmig na kondisyon. Kontrolin sa resistant variety at balanseng abono.', 'k' => ['rice blast', 'sakit', 'dahon']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is bacterial leaf blight?', 'a' => 'Bacterial leaf blight (Xanthomonas oryzae) shows yellowish-white, water-soaked lesions with wavy margins starting from the leaf tip or edges, often worse after typhoons or wind-driven rain that wounds leaves, and with excess nitrogen. Use resistant varieties and balanced fertilization.', 'k' => ['bacterial leaf blight', 'disease', 'xanthomonas', 'leaf blight']],
                'tl' => ['q' => 'Ano ang bacterial leaf blight?', 'a' => 'Ang bacterial leaf blight (Xanthomonas oryzae) ay may dilaw-puting sugat na parang basa sa tip o gilid ng dahon, kadalasang lumalala pagkatapos ng bagyo o malakas na ulan na sumusugat sa dahon, at kapag sobra sa nitrogen. Gamitin ang resistant variety at balanseng abono.', 'k' => ['bacterial leaf blight', 'sakit', 'dahon']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is sheath blight?', 'a' => 'Sheath blight (Rhizoctonia solani) causes oval, greenish-gray lesions on the leaf sheath near the waterline that expand upward, common in dense planting with high humidity. Manage with proper plant spacing, balanced fertilization, and fungicide for severe cases.', 'k' => ['sheath blight', 'disease', 'rhizoctonia', 'leaf sheath']],
                'tl' => ['q' => 'Ano ang sheath blight?', 'a' => 'Ang sheath blight (Rhizoctonia solani) ay may oval na kulay abo-berdeng sugat sa sheath ng dahon malapit sa tubig na kumakalat paitaas, karaniwan sa siksik na tanim at mahalumigmig na kondisyon. Pangasiwaan sa tamang espasyo at balanseng abono.', 'k' => ['sheath blight', 'sakit', 'rhizoctonia']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What is Tungro virus?', 'a' => 'Tungro is a virus transmitted by the green leafhopper, causing yellow-orange discoloration (especially in younger leaves) and stunted growth with reduced tillering. It is most damaging when infection happens early. Control the leafhopper vector, use resistant varieties, and remove infected plants early.', 'k' => ['tungro', 'disease', 'virus', 'green leafhopper', 'stunted growth']],
                'tl' => ['q' => 'Ano ang Tungro virus?', 'a' => 'Ang Tungro ay virus na dala ng green leafhopper, sanhi ng dilaw-kahel na kulay (lalo na sa batang dahon) at stunted growth na may kaunting tiller. Pinakamasama kapag naimpeksyon nang maaga. Kontrolin ang leafhopper, gamitin ang resistant variety, at alisin agad ang apektadong halaman.', 'k' => ['tungro', 'virus', 'green leafhopper', 'stunted']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'Why are my rice leaves turning yellow?', 'a' => 'Yellowing leaves usually point to one of a few causes: (1) nitrogen deficiency -- pale yellow starting from older, lower leaves, spread evenly across the field; (2) Tungro virus, spread by green leafhopper -- patchy yellow-orange discoloration with stunted growth; (3) waterlogging or poor drainage. Check the pattern across your field, and confirm with your MAO technician before treating.', 'k' => ['yellow leaves', 'yellowing', 'turning yellow', 'tungro', 'nitrogen deficiency']],
                'tl' => ['q' => 'Bakit dumidilaw ang dahon ng palay ko?', 'a' => 'Karaniwang dahilan ng pagdilaw ng dahon: (1) kulang sa nitrogen -- dilaw mula sa matatandang dahon, pantay sa buong bukid; (2) Tungro virus na dala ng green leafhopper -- batik-batik na dilaw-kahel kasabay ng stunted growth; (3) tubig na hindi umaagos nang maayos. Kumpirmahin sa MAO technician bago gumamot.', 'k' => ['dilaw na dahon', 'tungro', 'abono', 'nitrogen', 'stunting']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What is Alternate Wetting and Drying (AWD) irrigation?', 'a' => 'AWD lets the field dry until the water level drops about 15 cm below the surface before re-flooding. It saves water and reduces methane emissions while maintaining yield, as long as it is not applied during flowering or other critical growth stages.', 'k' => ['awd', 'alternate wetting and drying', 'irrigation', 'water saving']],
                'tl' => ['q' => 'Ano ang Alternate Wetting and Drying (AWD) irrigation?', 'a' => 'Sa AWD, hinahayaang matuyo ang bukid hanggang bumaba ang tubig nang mga 15 cm sa ilalim ng ibabaw bago muling pabahain. Nakakatipid ito ng tubig, pero huwag gawin habang flowering o iba pang kritikal na yugto.', 'k' => ['awd', 'irigasyon', 'tubig']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How should irrigation change across the rice growth stages?', 'a' => 'Keep the field flooded during crop establishment and flowering, since these are the most water-critical stages. You can apply AWD during the vegetative/tillering stage to save water without harming yield.', 'k' => ['irrigation schedule', 'growth stage', 'flooded field', 'water management']],
                'tl' => ['q' => 'Paano dapat magbago ang irigasyon sa iba\'t ibang growth stage?', 'a' => 'Panatilihing may tubig ang bukid habang crop establishment at flowering, dahil ito ang pinaka-kritikal na yugto para sa tubig. Puwedeng gamitin ang AWD sa vegetative/tillering stage para makatipid ng tubig.', 'k' => ['irigasyon', 'growth stage', 'tubig']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'How is irrigation different for rainfed versus irrigated farms?', 'a' => 'Rainfed farms depend on rainfall timing, so planting should align with the reliable start of the wet season. Irrigated farms have more schedule flexibility but should still avoid waterlogging outside the flooded growth stages.', 'k' => ['rainfed', 'irrigated', 'irrigation', 'farm type']],
                'tl' => ['q' => 'Paano naiiba ang irigasyon sa rainfed at irrigated na bukid?', 'a' => 'Ang rainfed farm ay umaasa sa oras ng ulan, kaya dapat itugma ang pagtatanim sa maaasahang pagsisimula ng tag-ulan. Ang irrigated farm ay mas flexible ang schedule pero iwasan pa rin ang labis na tubig sa labas ng flooded stage.', 'k' => ['rainfed', 'irrigated', 'irigasyon']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How do I know when rice is ready to harvest?', 'a' => 'Rice is typically ready to harvest when about 80-85% of grains have turned golden yellow, the panicle bends downward, and grains feel hard when bitten. This is usually 30-35 days after flowering, or roughly 105-130 days after planting depending on variety.', 'k' => ['harvest maturity', 'when to harvest', 'harvesting practices', 'ready to harvest']],
                'tl' => ['q' => 'Paano malalaman kung handa nang anihin ang palay?', 'a' => 'Karaniwang handa nang anihin ang palay kapag 80-85% ng butil ay naging ginintuang dilaw na, nakayuko ang uhay, at matigas na ang butil kapag kinagat. Karaniwang 30-35 araw pagkatapos mamulaklak, o 105-130 araw pagkatanim depende sa variety.', 'k' => ['ani', 'kailan aanihin', 'harvest', 'maturity']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What are the recommended harvesting methods for rice?', 'a' => 'Rice can be harvested manually with a sickle or mechanically with a combine harvester. Harvest promptly at maturity to avoid grain shattering and yield loss from lodging or overripening.', 'k' => ['harvesting method', 'sickle', 'combine harvester', 'harvest']],
                'tl' => ['q' => 'Ano ang rekomendadong paraan ng pag-ani ng palay?', 'a' => 'Puwedeng anihin ang palay nang manual gamit ang karit o mekanikal gamit ang combine harvester. Anihin agad kapag hinog para maiwasan ang pagkalat ng butil at pagkawala ng ani.', 'k' => ['pag-ani', 'karit', 'harvester', 'ani']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'How should I dry and store harvested palay?', 'a' => 'Dry palay down to about 14% moisture content before storing. Store it in a clean, dry, pest-free area in sacks or silos to preserve grain quality and prevent mold or insect damage.', 'k' => ['drying', 'storage', 'palay', 'moisture content', 'post-harvest']],
                'tl' => ['q' => 'Paano dapat patuyuin at itago ang naaning palay?', 'a' => 'Patuyuin ang palay hanggang mga 14% moisture content bago itago. Itago sa malinis, tuyo, at walang pesteng lugar gamit ang sako o silo para mapanatili ang kalidad ng butil.', 'k' => ['pagpapatuyo', 'imbakan', 'palay', 'post-harvest']],
            ],
        ];
    }

    private function systemHelpTopics(): array
    {
        $category = IntentDetectionService::SYSTEM_HELP;

        return [
            [
                'category' => $category, 'confidence' => 88,
                'en' => ['q' => 'What is Random Forest and why does iClimate use it?', 'a' => 'Random Forest is an ensemble machine-learning model that builds many decision trees on random subsets of data and features, then averages their predictions. This reduces overfitting and improves accuracy over a single tree, which is why iClimate uses it for rice yield prediction.', 'k' => ['random forest', 'machine learning', 'ensemble model', 'decision tree']],
                'tl' => ['q' => 'Ano ang Random Forest at bakit ito ginagamit ng iClimate?', 'a' => 'Ang Random Forest ay isang ensemble machine-learning model na bumubuo ng maraming decision tree gamit ang random na bahagi ng data, pagkatapos ay pinagsasama-sama ang resulta. Binabawasan nito ang overfitting at pinapabuti ang accuracy, kaya ginagamit ito ng iClimate sa yield prediction.', 'k' => ['random forest', 'machine learning', 'decision tree']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What is Linear Regression in the iClimate prediction models?', 'a' => 'Linear Regression is a simple statistical model that fits a straight-line relationship between input features (like rainfall and temperature) and an outcome (like yield). It is useful as a baseline but is less flexible than tree-based models for complex weather-yield relationships.', 'k' => ['linear regression', 'machine learning', 'statistical model']],
                'tl' => ['q' => 'Ano ang Linear Regression sa mga prediction model ng iClimate?', 'a' => 'Ang Linear Regression ay simpleng statistical model na gumagawa ng straight-line na relasyon sa pagitan ng input (gaya ng ulan at temperatura) at resulta (gaya ng ani). Mainam ito bilang baseline pero hindi kasing flexible ng tree-based models.', 'k' => ['linear regression', 'machine learning']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What is Gradient Boosting?', 'a' => 'Gradient Boosting is an ensemble method that builds decision trees sequentially, with each new tree correcting the errors of the previous ones. It often achieves strong accuracy but requires careful tuning to avoid overfitting.', 'k' => ['gradient boosting', 'machine learning', 'ensemble model']],
                'tl' => ['q' => 'Ano ang Gradient Boosting?', 'a' => 'Ang Gradient Boosting ay ensemble method na sunod-sunod na bumubuo ng decision tree, kung saan itinatama ng bawat bagong tree ang error ng nauna. Kadalasang mataas ang accuracy nito pero kailangan ng maingat na tuning.', 'k' => ['gradient boosting', 'machine learning']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What does the confidence score in a prediction mean?', 'a' => 'The confidence score reflects how reliable a specific prediction is, based on factors like data quality, how well the input matches the training data, and model agreement. A higher confidence score means the prediction is more trustworthy.', 'k' => ['confidence score', 'prediction reliability', 'how accurate']],
                'tl' => ['q' => 'Ano ang ibig sabihin ng confidence score sa prediction?', 'a' => 'Ang confidence score ay sumasalamin kung gaano kareliable ang isang partikular na prediction, base sa kalidad ng data at pagkakatugma sa training data. Mas mataas na confidence score, mas mapagkakatiwalaan ang prediction.', 'k' => ['confidence score', 'gaano ka-reliable']],
            ],
            [
                'category' => $category, 'confidence' => 86,
                'en' => ['q' => 'What is RMSE (Root Mean Squared Error)?', 'a' => 'RMSE measures the average prediction error in the same unit as the target -- for example, tons per hectare for yield. A lower RMSE means the model\'s predictions are, on average, closer to the actual values.', 'k' => ['rmse', 'root mean squared error', 'model accuracy']],
                'tl' => ['q' => 'Ano ang RMSE (Root Mean Squared Error)?', 'a' => 'Sinusukat ng RMSE ang average prediction error gamit ang parehong unit ng target, hal. tons kada ektarya para sa ani. Mas mababang RMSE, mas malapit ang prediction sa tunay na value.', 'k' => ['rmse', 'model accuracy']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What is MAE (Mean Absolute Error)?', 'a' => 'MAE is the average absolute difference between predicted and actual values. It is similar to RMSE but less sensitive to a few large outlier errors, giving a more typical picture of everyday prediction error.', 'k' => ['mae', 'mean absolute error', 'model accuracy']],
                'tl' => ['q' => 'Ano ang MAE (Mean Absolute Error)?', 'a' => 'Ang MAE ay average na absolute na pagkakaiba sa pagitan ng prediction at tunay na value. Katulad ito ng RMSE pero hindi gaanong apektado ng ilang malaking outlier error.', 'k' => ['mae', 'model accuracy']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What is R-squared (R2 score)?', 'a' => 'The R-squared (R2) score shows how much of the variation in actual outcomes the model explains, on a scale from 0 to 1. A score closer to 1 means the model explains the data very well.', 'k' => ['r2', 'r-squared', 'model accuracy']],
                'tl' => ['q' => 'Ano ang R-squared (R2 score)?', 'a' => 'Ipinapakita ng R-squared (R2) score kung gaano karaming variation sa tunay na resulta ang naipaliwanag ng model, mula 0 hanggang 1. Mas malapit sa 1, mas mahusay ipinapaliwanag ng model ang data.', 'k' => ['r2', 'r-squared', 'model accuracy']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How do I read the iClimate dashboard?', 'a' => 'The dashboard summarizes current weather, your predicted yield, planting and irrigation recommendations, and recent alerts or notifications relevant to your farm and barangay -- check it regularly for updated guidance.', 'k' => ['dashboard', 'how to read', 'explain the dashboard']],
                'tl' => ['q' => 'Paano basahin ang iClimate dashboard?', 'a' => 'Ang dashboard ay buod ng kasalukuyang panahon, predicted yield, rekomendasyon sa pagtatanim at irigasyon, at kamakailang alerto o notification na may kinalaman sa bukid at barangay mo.', 'k' => ['dashboard', 'paano basahin']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How do I read the weather chart on the dashboard?', 'a' => 'The weather chart shows recent and forecasted rainfall and temperature trends, which you can use to plan irrigation timing and upcoming planting or fertilizer decisions.', 'k' => ['weather chart', 'graph', 'explain this chart']],
                'tl' => ['q' => 'Paano basahin ang weather chart sa dashboard?', 'a' => 'Ipinapakita ng weather chart ang kamakailan at inaasahang trend ng ulan at temperatura, magagamit mo ito sa pagplano ng irigasyon at pagtatanim.', 'k' => ['weather chart', 'graph']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How do I read the yield chart or graph?', 'a' => 'The yield chart compares your predicted yield against historical or local average yields for your season and barangay, helping you see whether this season looks better or worse than usual.', 'k' => ['yield chart', 'yield graph', 'explain this graph']],
                'tl' => ['q' => 'Paano basahin ang yield chart o graph?', 'a' => 'Inikumpara ng yield chart ang predicted yield mo sa historical o average na ani ng barangay mo para sa season, para makita kung mas mabuti o mas mababa ang season na ito.', 'k' => ['yield chart', 'graph']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How do I read the heat map and its legend?', 'a' => 'The heat map colors represent climate risk level per barangay -- for example green for low, yellow for moderate, orange for high, and red for severe risk of flood, drought, typhoon, or heat.', 'k' => ['heat map', 'heatmap', 'map legend', 'risk level']],
                'tl' => ['q' => 'Paano basahin ang heat map at ang legend nito?', 'a' => 'Ang kulay sa heat map ay kumakatawan sa antas ng climate risk kada barangay -- hal. berde para sa mababa, dilaw para sa katamtaman, orange para sa mataas, at pula para sa malubhang panganib ng baha, tagtuyot, bagyo, o init.', 'k' => ['heat map', 'legend', 'risk']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'What do notifications on my dashboard mean?', 'a' => 'Notifications alert you about new advisories, weather warnings, and announcements connected to your account or barangay -- open them to see the full advisory or announcement text.', 'k' => ['notifications', 'dashboard notifications', 'alerts']],
                'tl' => ['q' => 'Ano ang ibig sabihin ng notifications sa dashboard ko?', 'a' => 'Inaalertuhan ka ng notifications tungkol sa bagong advisory, weather warning, at announcement na konektado sa account o barangay mo -- buksan ito para makita ang buong detalye.', 'k' => ['notifications', 'alerto']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How do I change my password?', 'a' => 'Go to your account or profile settings, select "change password," enter your current password and a new password, then save the change.', 'k' => ['change password', 'account settings', 'update password']],
                'tl' => ['q' => 'Paano ko babaguhin ang password ko?', 'a' => 'Pumunta sa account o profile settings, piliin ang "change password," ilagay ang kasalukuyang password at bagong password, pagkatapos i-save.', 'k' => ['change password', 'baguhin password']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'How do I update my profile information?', 'a' => 'Go to your profile page and edit your farm area, barangay, farm type, or contact details, then save the changes so iClimate recommendations stay accurate for your farm.', 'k' => ['update profile', 'edit profile', 'farm information']],
                'tl' => ['q' => 'Paano ko i-a-update ang profile information ko?', 'a' => 'Pumunta sa profile page at i-edit ang farm area, barangay, farm type, o contact details, pagkatapos i-save para tumpak ang rekomendasyon ng iClimate para sa bukid mo.', 'k' => ['update profile', 'i-edit profile']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'How accurate is the iClimate prediction?', 'a' => 'Prediction accuracy depends on model performance (see RMSE/R2 metrics) and data quality. Predictions are decision-support estimates, not guarantees, and should be combined with your own field observation before acting.', 'k' => ['how accurate', 'accuracy', 'prediction reliability']],
                'tl' => ['q' => 'Gaano kaaccurate ang prediction ng iClimate?', 'a' => 'Nakadepende ang accuracy sa performance ng model (tingnan ang RMSE/R2) at kalidad ng data. Ang prediction ay decision-support estimate lamang, hindi garantiya, kaya ikumbina pa rin sa sariling obserbasyon sa bukid.', 'k' => ['gaano kaaccurate', 'accuracy']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'Where does the weather data in iClimate come from?', 'a' => 'Current forecasts primarily come from locally recorded climate data and, when configured, a live weather API. Historical climate records are also used to calibrate offline predictions.', 'k' => ['weather data source', 'where does data come from', 'data source']],
                'tl' => ['q' => 'Saan galing ang weather data ng iClimate?', 'a' => 'Ang mga forecast ay pangunahing galing sa lokal na naitalang climate data at, kung naka-configure, sa live weather API. Ginagamit din ang historical climate records para i-calibrate ang offline prediction.', 'k' => ['saan galing ang data', 'weather data']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'How often is the rice yield prediction updated?', 'a' => 'Predictions are generated fresh each time you ask a question, using the latest available climate records and your farm data, so you always get the most current estimate.', 'k' => ['how often updated', 'prediction update frequency']],
                'tl' => ['q' => 'Gaano kadalas nauupdate ang yield prediction?', 'a' => 'Bawat magtanong ka, bagong prediction ang gagawin gamit ang pinakahuling climate records at farm data mo, kaya laging updated ang natatanggap mong estimate.', 'k' => ['gaano kadalas', 'update']],
            ],
        ];
    }

    private function barangayInfoTopics(): array
    {
        $category = IntentDetectionService::BARANGAY_INFO;

        return [
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What barangay information can I ask iClimate about?', 'a' => 'You can ask about rice production, average yield, farm area, and weather or climate risk for your own barangay or another barangay in Lian, Batangas -- for example, "What is the production in Barangay Matabungkay?"', 'k' => ['barangay information', 'what can i ask', 'barangay data']],
                'tl' => ['q' => 'Anong impormasyon tungkol sa barangay ang puwede kong itanong sa iClimate?', 'a' => 'Puwede kang magtanong tungkol sa produksyon ng palay, average yield, farm area, at panganib sa panahon para sa barangay mo o ibang barangay sa Lian, Batangas -- hal. "Ano ang produksyon sa Barangay Matabungkay?"', 'k' => ['barangay', 'anong itatanong']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'How can I find out which barangay has the highest rice production?', 'a' => 'You can ask "Which barangay has the highest yield?" -- detailed cross-barangay rankings are available to MAO staff accounts, while farmer accounts can ask about their own or a specific named barangay.', 'k' => ['barangay production', 'highest production', 'barangay ranking']],
                'tl' => ['q' => 'Paano ko malalaman kung aling barangay ang may pinakamataas na produksyon ng palay?', 'a' => 'Puwede mong itanong ang "Aling barangay ang may pinakamataas na ani?" -- available ang detalyadong ranking sa mga MAO staff account, habang ang farmer account ay puwedeng magtanong tungkol sa sariling o partikular na barangay.', 'k' => ['produksyon', 'pinakamataas', 'barangay']],
            ],
            [
                'category' => $category, 'confidence' => 80,
                'en' => ['q' => 'Can I ask about the weather or flood risk of a specific barangay?', 'a' => 'Yes -- ask about the flood, drought, typhoon, or heat risk level for a specific barangay, based on the iClimate heat map data (for example, "What is the risk level in Barangay Lumaniag?").', 'k' => ['barangay weather', 'barangay risk', 'flood risk barangay']],
                'tl' => ['q' => 'Puwede ko bang itanong ang panahon o flood risk ng partikular na barangay?', 'a' => 'Oo -- itanong ang antas ng peligro sa baha, tagtuyot, bagyo, o init para sa partikular na barangay, batay sa heat map data ng iClimate (hal. "Ano ang risk level sa Barangay Lumaniag?").', 'k' => ['barangay', 'panganib', 'baha']],
            ],
        ];
    }

    private function maoReportTopics(): array
    {
        $category = IntentDetectionService::MAO_REPORTS;

        return [
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What kinds of reports can MAO staff request from iClimate?', 'a' => 'MAO Personnel accounts can ask for farmer reports, yield reports, weather reports, barangay reports, and annual production summaries directly through the assistant.', 'k' => ['mao reports', 'what reports', 'farmer report', 'yield report']],
                'tl' => ['q' => 'Anong uri ng report ang puwedeng hingin ng MAO staff sa iClimate?', 'a' => 'Ang MAO Personnel account ay puwedeng humingi ng farmer report, yield report, weather report, barangay report, at annual production summary direkta sa assistant.', 'k' => ['mao report', 'anong report']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What does a yield or production report show?', 'a' => 'A yield/production report summarizes average yield, total production, and area harvested per barangay, season, or year from recorded rice production data.', 'k' => ['yield report', 'production report', 'production summary']],
                'tl' => ['q' => 'Ano ang ipinapakita ng yield o production report?', 'a' => 'Ang yield/production report ay buod ng average yield, kabuuang produksyon, at area harvested kada barangay, season, o taon mula sa naitalang rice production data.', 'k' => ['yield report', 'production report']],
            ],
            [
                'category' => $category, 'confidence' => 82,
                'en' => ['q' => 'What does a barangay ranking report show?', 'a' => 'A barangay ranking report orders barangays by average yield or total production, helping MAO staff identify which areas may need more support, inputs, or advisories.', 'k' => ['barangay ranking', 'barangay report', 'highest yield barangay']],
                'tl' => ['q' => 'Ano ang ipinapakita ng barangay ranking report?', 'a' => 'Ang barangay ranking report ay nag-aayos ng mga barangay ayon sa average yield o kabuuang produksyon, para matukoy ng MAO staff kung aling lugar ang mas nangangailangan ng suporta.', 'k' => ['barangay ranking', 'ranking report']],
            ],
        ];
    }

    private function itSystemStatusTopics(): array
    {
        $category = IntentDetectionService::IT_SYSTEM_STATUS;

        return [
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What system status information can IT personnel check?', 'a' => 'IT Expert accounts can ask the assistant for the number of registered users, database connectivity status, farming-AI API status, a recent system error summary, and whether the system is in maintenance mode.', 'k' => ['system status', 'it personnel', 'what can it check']],
                'tl' => ['q' => 'Anong impormasyon sa system status ang puwedeng tignan ng IT personnel?', 'a' => 'Ang IT Expert account ay puwedeng magtanong sa assistant tungkol sa bilang ng rehistradong user, database connectivity, farming-AI API status, buod ng kamakailang error, at kung nasa maintenance mode ang system.', 'k' => ['system status', 'it personnel']],
            ],
            [
                'category' => $category, 'confidence' => 84,
                'en' => ['q' => 'What is the backup and maintenance policy for iClimate?', 'a' => 'iClimate does not currently have an automated backup system configured, so IT personnel should manually back up the database and files on a regular schedule until an automated solution is set up. Maintenance mode status can be checked to see if the system is temporarily down for updates.', 'k' => ['backup policy', 'maintenance policy', 'backup reminder', 'system maintenance']],
                'tl' => ['q' => 'Ano ang backup at maintenance policy ng iClimate?', 'a' => 'Wala pang automated backup system ang iClimate sa ngayon, kaya dapat manual na i-back up ng IT personnel ang database at files nang regular hanggang magkaroon ng automated solution. Puwede ring tignan ang maintenance mode status ng system.', 'k' => ['backup', 'maintenance', 'sistema']],
            ],
        ];
    }
}
