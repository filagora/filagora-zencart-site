<?php

/*
 * The site's pages and their live blocks, as the live site has them (7 Nov 2026), for the
 * `create_site_content` migration in the Zen Cart profile. Addresses that belong to the environment are
 * placeholders, filled in from `filagora-zencart.content.placeholders` (see config/filagora-zencart.php).
 */

return [
    [
        'slug' => 'privacy',
        'custom_route' => '/privacy',
        'title' => 'Zen Cart Privacy Policy',
        'excerpt' => 'How Zen Cart collects, uses, and protects personal information.',
        'seo_title' => 'Privacy Policy - Zen Cart',
        'seo_description' => 'Zen Cart privacy policy and data-handling practices.',
        'published_at' => '2026-08-13 17:02:23',
        'blocks' => [
            [
                'type' => 'raw_html',
                'name' => 'Privacy Policy',
                'sort_order' => 10,
                'data' => [
                    'html' => '<h2>Zen Cart&reg; Privacy Policy</h2>
<p>Your privacy is important to us. To better protect your privacy, Zen Ventures, LLC (herein also referred to interchangeably as Zen Cart&reg;) provides this notice explaining our online information practices and the choices you can make about the way your information is collected and used. To make this notice easy to find, Zen Cart&reg; makes a link to it available on our homepage and at every point where personally identifiable information may be requested.</p>

<h3>The Information Zen Cart&reg; Collects</h3>
<p>This notice applies to all information collected or submitted on the Zen Cart&reg; web site. All information marked with an asterisk (*) is required for a particular service.</p>
<p>Our forums require you to register before being able to post. All of the information collected during forum registration is available to all forum members and the staff and volunteers of Zen Cart&reg;. The information collected for the forum is:</p>
<ul>
  <li>Your Name*</li>
  <li>Email address*</li>
  <li>A Username*</li>
  <li>A web site address, other contact methods, location, etc are all optional.</li>
</ul>
<p>Your IP address may also be logged. IP addresses may be used in fraud prevention and/or to prosecute perpetrators of fraud or other abuses.</p>
<p>On some pages, you may order products, make requests, and register to receive materials. The information collected for these sections is strictly confidential and only shared with shippers, payment processors and/or selected partners as needed in order to fulfill the services you have selected to engage with us. The types of personal information collected at these pages are:</p>
<ul>
  <li>Your Name*</li>
  <li>Billing and Shipping Address(es)*</li>
  <li>Email address*</li>
  <li>Phone number*</li>
  <li>Possibly credit card number or other payment information. If a credit card number is collected it is only securely transmitted to a 3rd-party payment gateway and your credit card number is never stored on our servers.</li>
</ul>
<p>On some pages, you can submit information about other people. For example, if you order a gift online and want it sent directly to the recipient, you will need to submit the recipient\'s address. The information collected for these sections is strictly confidential and only shared with shippers as needed. In this circumstance, the types of personal information collected are:</p>
<ul>
  <li>Recipient\'s Name*</li>
  <li>Recipient\'s Address*</li>
  <li>Phone Number*</li>
</ul>

<h3>Newsletters</h3>
<p>You may optionally choose to sign up for a newsletter by entering your email address in the appropriate place on our support website. When you do so, Zen Ventures, LLC stores your subscription status, consent details, and newsletter delivery history so we can send newsletters, manage unsubscribes, and avoid sending to suppressed addresses. We may use a configured email delivery provider to deliver newsletter messages and process delivery events such as bounces, complaints, and unsubscribes. You can opt out of the newsletter at any time.</p>

<h3>The Way Zen Cart&reg; Uses Information</h3>
<p>Zen Cart&reg; uses the information you provide about yourself when placing an order only to complete that order. We do not share this information with outside parties except to the extent necessary to complete that order.</p>
<p>Zen Cart&reg; uses the information you provide about someone else when placing an order only to ship the product and to confirm delivery. We do not share this information with outside parties except to the extent necessary to complete that order.</p>
<p>Zen Cart&reg; uses return email addresses to answer the email Zen Cart&reg; receives. Such addresses are not used for any other purpose and are not shared with outside parties except to facilitate this exchange of email.</p>
<p>You can register with the Zen Cart&reg; web site if you would like to use our forum, receive newsletters, list your site in the showcase, and/or other services. Information you submit on the Zen Cart&reg; web site may be shared with selected partners.</p>
<p>If you choose to subscribe to a forum thread or plugin, we store that subscription against your account so we can send the update emails or digests you asked for. You can manage or withdraw those subscriptions at any time from your profile subscriptions page, and subscription emails include a direct link back to those controls.</p>
<p>We also keep limited accountability records for subscription changes, such as the time of the action and associated request metadata, to help us demonstrate and protect the integrity of notification preferences.</p>
<p>Zen Cart&reg; may use non-identifying and aggregate information to better design the Zen Cart&reg; web site and to share with advertisers. For example, Zen Cart&reg; may tell an advertiser that X number of individuals visited a certain area on our Zen Cart&reg; web site, or that Y number of men and Z number of women filled out our registration form, but Zen Cart&reg; would not disclose anything that could be used to identify those individuals.</p>
<p>Finally, Zen Cart&reg; never uses or shares the personally identifiable information provided to us online in ways unrelated to the ones described above without also providing you an opportunity to opt-out or otherwise prohibit such unrelated uses.</p>

<h3>Cookies</h3>
<p>We use cookies on the Zen Cart support site to track user activity and to provide certain functionality. These cookies are not used by Zen Ventures, LLC for any advertising purposes.</p>
<p>The following cookies are used by the Zen Cart Support Site to facilitate your login, your posting permissions, your unread markers, your account/viewing preferences, your content subscriptions, and other features enabled by the software on our site: zcsupport_cpsession, zcsupport_lastactivity, zcsupport_lastvisit, zcsupport_password, zcsupport_sessionhash, zcsupport_thread_lastview, zcsupport_userid, zcsupport_userstyleid, editor_height, vbulletin_collapse.</p>
<p>There are some additional third-party services used for monitoring and improving services provided by Zen Ventures, LLC, including but not limited to tools like Google Analytics (cookies prefixed by __utm) for statistical and performance monitoring and site tuning, Twitter for providing information on our site from twitter feeds, Facebook for enabling display of Facebook content within our site, and YouTube for enabling display of video content within our site. These third-party services are used only to enhance our site, and the cookies they use are controlled by them.</p>

<h3>The Zen Cart&reg; Commitment To Data Security</h3>
<p>To prevent unauthorized access, maintain data accuracy, and ensure the correct use of information, Zen Cart&reg; has put in place appropriate physical, electronic, and managerial procedures to safeguard and secure the information Zen Cart&reg; collects online.</p>

<h3>The Zen Cart&reg; Commitment To Children\'s Privacy</h3>
<p>Protecting the privacy of the very young is especially important. For that reason, Zen Cart&reg; never collects or maintains information at our Zen Cart&reg; web site from those we actually know are under 13, and no part of our Zen Cart&reg; web site is structured to attract anyone under 13.</p>

<h3>How You Can Access Or Correct Your Information</h3>
<p>You can access all your personally identifiable information that Zen Cart&reg; collects online and maintains by logging in and clicking on My Controls or My Account in the menu. We use this procedure to better safeguard your information.</p>

<h3>How To Contact Us</h3>
<p>Should you have other questions or concerns about these privacy policies, please <!--email_off--><a href="mailto:{support_email}">contact us</a><!--/email_off-->.</p>',
                ],
            ],
        ],
    ],
    [
        'slug' => 'download',
        'custom_route' => '/download',
        'title' => 'Download Zen Cart',
        'excerpt' => 'Download the latest Zen Cart release and follow the next steps for setup.',
        'seo_title' => 'Download Zen Cart',
        'seo_description' => 'Download the latest Zen Cart release, review installation steps, and check technical requirements.',
        'published_at' => '2026-08-13 17:02:24',
        'blocks' => [
            [
                'type' => 'promo_hero',
                'name' => 'Download Hero',
                'sort_order' => 10,
                'data' => [
                    'title' => 'You\'re making a great choice!',
                    'body' => 'Just one step away from being independent with your online store!',
                    'benefits' => [
                        'No monthly cost for license',
                        'Unlimited freedom to customize your store',
                        'We are trusted all over the world',
                    ],
                    'cta_label' => 'Download Zen Cart',
                    'cta_url' => 'https://github.com/zencart/zencart/archive/refs/tags/v2.2.2.zip',
                    'badge' => '2.2.2',
                    'meta_label' => 'SHA256',
                    'meta_value' => '5fdb86e97460975b5f7a5e25affd3616b2470b96001aa3f1192bd0cc4c0efadc',
                    'meta_help_label' => 'verify file integrity',
                    'meta_help_url' => '{docs}/user/installing/validate_sha/',
                    'secondary_link_label' => 'Click here to download older versions',
                    'secondary_link_url' => 'https://github.com/zencart/zencart/releases',
                ],
            ],
            [
                'type' => 'checklist_callout',
                'name' => 'What\'s Next',
                'sort_order' => 20,
                'data' => [
                    'title' => 'What\'s Next',
                    'subtitle' => 'Learn how to get your 10-Minute Deployment',
                    'items' => [
                        [
                            'label' => 'Extract & Upload',
                            'body' => 'Unzip the downloaded file and upload the contents to your server\'s public directory (e.g., public_html) via SFTP or your hosting file manager.',
                        ],
                        [
                            'label' => 'Prepare the Database',
                            'body' => 'Create a new, empty MySQL or MariaDB database and user in your hosting control panel.',
                        ],
                        [
                            'label' => 'Run the Installer',
                            'body' => 'Navigate to your store\'s URL in any web browser. The built-in zc_install wizard will automatically launch and guide you through the final database connection and admin setup.',
                        ],
                    ],
                    'callout_title' => 'Need a detailed walkthrough?',
                    'callout_body' => 'Head over to our',
                    'callout_link_label' => 'Official Installation Guide',
                    'callout_link_url' => '{docs}/release/1.readme_installation',
                ],
            ],
            [
                'type' => 'split_info_panel',
                'name' => 'Technical Information',
                'sort_order' => 30,
                'data' => [
                    'title' => 'Technical Information',
                    'subtitle' => 'What do I need to run Zen Cart®?',
                    'body_title' => 'Server Compatibility',
                    'paragraphs' => [
                        [
                            'text' => 'The lowest level hosting solution that offers modern PHP, a web server (like Apache or Nginx), and a MySQL database is usually sufficient to get started.',
                        ],
                        [
                            'text' => 'While many hosting companies provide a "one-click" Zen Cart® auto-installer for quick tryouts, we highly recommend performing a manual installation for live production environments. This ensures you have complete control over your security and file structure.',
                        ],
                    ],
                    'cta_label' => 'View Detailed Server Requirements',
                    'cta_url' => '{docs}/user/first_steps/server_requirements/',
                    'specs_title' => 'Minimum Specs (v2.1+)',
                    'specs' => [
                        [
                            'icon_class' => 'fa-brands fa-php',
                            'label' => 'PHP',
                            'value' => '8.1 - 8.3+',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-server',
                            'label' => 'Web Server',
                            'value' => 'Apache / Nginx',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-database',
                            'label' => 'Database',
                            'value' => 'MySQL 5.7+ / MariaDB',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'icon_callout',
                'name' => 'Community CTA',
                'sort_order' => 40,
                'data' => [
                    'title' => 'Don\'t build in isolation!',
                    'body' => 'Whether you need help configuring your first payment gateway, styling your template, or discovering hidden features, our community has your back! Join the official Zen Cart forum to access over 20 years of collective e-commerce wisdom, tips and tricks from thousands of active store owners and developers.',
                    'cta_label' => 'Join the Official Forum',
                    'cta_url' => '/forums',
                    'icon_class' => 'fa-solid fa-users-gear',
                ],
            ],
        ],
    ],
    [
        'slug' => 'partners',
        'custom_route' => '/services',
        'title' => 'partners',
        'excerpt' => 'Recommended service providers, compatible hosting, and open source sponsors for Zen Cart.',
        'seo_title' => 'Recommended Services - Zen Cart',
        'seo_description' => 'Recommended Zen Cart services, hosts, and sponsors.',
        'published_at' => '2026-08-13 17:02:24',
        'blocks' => [
            [
                'type' => 'services_hero',
                'name' => 'Services Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'Recommended',
                    'title' => 'Services',
                    'body' => 'When you sign up for these services using the links provided, those services pay a commission fee back to Zen Cart to help support the Zen Cart project.',
                ],
            ],
            [
                'type' => 'link_grid',
                'name' => 'Service Categories',
                'sort_order' => 20,
                'data' => [
                    'title' => 'Service Categories',
                    'body' => '',
                    'items' => [
                        [
                            'title' => 'Payment Processing',
                            'url' => '/services/payment-processing',
                            'body' => 'Information on payment gateways which allow you to collect money from your customers.',
                        ],
                        [
                            'title' => 'Business Services Providers',
                            'url' => '/services/business-services',
                            'body' => 'Numerous additional features which may help make running your online store more successful.',
                        ],
                        [
                            'title' => 'Custom Programming Support',
                            'url' => '/services/custom-programming',
                            'body' => 'Custom programming and discount modules from ThatSoftwareGuy.com and other providers.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'Hosting Providers',
                'sort_order' => 30,
                'data' => [
                    'title' => 'Compatible Hosting and Service Providers',
                    'body' => '',
                    'surface' => 'panel',
                    'columns' => 2,
                    'items' => [
                        [
                            'name' => 'AEIIA',
                            'url' => 'https://www.aeiia.com',
                            'image_url' => 'https://www.aeiia.com/supp/templates/CloudSave-child/assets/img/logo.png',
                            'dark_logo_background' => false,
                            'body' => 'Managed Zen Cart hosting with security-oriented infrastructure. PCI, HIPAA, PIPEDA & PHIPA compliance. VPS and dedicated servers with free migration and basic installation. Telephone and ticket support available.',
                            'tags' => [],
                            'logo_html' => null,
                            'badge' => null,
                            'cta_label' => null,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                        [
                            'name' => 'My Zen Cart Host',
                            'url' => 'https://myzencarthost.com',
                            'image_url' => 'https://myzencarthost.com/assets/img/logo.png',
                            'dark_logo_background' => false,
                            'body' => 'Specialized Zen Cart hosting built specifically for running Zen Cart stores with optimized server configurations.',
                            'tags' => [],
                            'logo_html' => null,
                            'badge' => null,
                            'cta_label' => null,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                        [
                            'name' => 'DigitalOcean',
                            'url' => 'https://www.awin1.com/cread.php?awinmid=123996&awinaffid=3116171',
                            'image_url' => '/storage/cms-media/partners/DO_Powered_by_Badge_blue.png',
                            'dark_logo_background' => false,
                            'body' => 'Self-managed hosting with SSD drives. Great for developers who want full control over their server environment. $200 instant credit available for new signups.
<br><br>
We earn commissions when you shop through the links below',
                            'tags' => [],
                            'logo_html' => null,
                            'badge' => null,
                            'cta_label' => null,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                        [
                            'name' => 'NodePing',
                            'url' => 'https://nodeping.com?rid=2011092618071CZGM',
                            'image_url' => 'https://nodeping.com/img/logo.svg',
                            'dark_logo_background' => true,
                            'body' => 'Server uptime monitoring service with email and SMS alerts. Keep tabs on your store\'s availability around the clock.',
                            'tags' => [],
                            'logo_html' => null,
                            'badge' => null,
                            'cta_label' => null,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'logo_grid',
                'name' => 'Open Source Sponsors',
                'sort_order' => 40,
                'data' => [
                    'title' => 'Open Source Sponsors',
                    'body' => 'These companies provide software and services to support Zen Cart development.',
                    'items' => [
                        [
                            'name' => 'PhpStorm',
                            'url' => 'https://www.jetbrains.com/phpstorm/',
                            'image_url' => '/storage/cms-media/partners/phpstorm.jpg',
                        ],
                        [
                            'name' => 'DigitalOcean',
                            'url' => 'https://m.do.co/c/d85f77b95caa',
                            'image_url' => '/storage/cms-media/partners/DO_Powered_by_Badge_blue.png',
                        ],
                        [
                            'name' => 'Navicat',
                            'url' => 'https://www.navicat.com/',
                            'image_url' => '/storage/cms-media/partners/Navicat-Premium.png',
                        ],
                        [
                            'name' => 'Atlassian',
                            'url' => 'https://www.atlassian.com/',
                            'image_url' => '/storage/cms-media/partners/logoAtlassianPNG.png',
                        ],
                        [
                            'name' => 'BrowserStack',
                            'url' => 'https://www.browserstack.com/',
                            'image_url' => '/storage/cms-media/partners/browserstack.svg',
                        ],
                        [
                            'name' => 'Tower',
                            'url' => 'https://www.git-tower.com/',
                            'image_url' => '/storage/cms-media/partners/tower2-logo-icon-subline-light.png',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Services CTA',
                'sort_order' => 50,
                'data' => [
                    'title' => 'Need Help With Your Store?',
                    'body' => 'Browse our forums or reach out to one of the recommended service providers above to get expert help with your Zen Cart store.',
                    'theme' => 'warm',
                    'primary_label' => 'Visit the Forums',
                    'primary_url' => '/forums',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'about',
        'custom_route' => '/about',
        'title' => 'About Zen Cart',
        'excerpt' => 'About the Zen Cart project, its goals, and the open-source philosophy behind it.',
        'seo_title' => 'About Zen Cart',
        'seo_description' => 'About the Zen Cart project and its open-source e-commerce philosophy.',
        'published_at' => '2026-08-13 17:02:24',
        'blocks' => [
            [
                'type' => 'about_hero',
                'name' => 'About Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'About The Project',
                    'title' => 'The Art of E-Commerce',
                    'body' => 'Zen Cart is a free, user-friendly, open source shopping cart system. The software is developed and maintained by a global group of like-minded shop owners, programmers, designers, and consultants who believe E-Commerce can - and should - be done differently.',
                ],
            ],
            [
                'type' => 'icon_card_grid',
                'name' => 'Ecosystem',
                'sort_order' => 20,
                'data' => [
                    'title' => 'Built for the Entire Ecosystem',
                    'body' => 'While other solutions seem to be complicated programming exercises, Zen Cart puts merchant and shopper requirements first.',
                    'items' => [
                        [
                            'title' => 'For Online Merchants',
                            'icon_class' => 'fa-solid fa-store',
                            'body' => 'Other programs are nearly impossible to install without an IT degree, but Zen Cart can be set up by anyone with basic computer skills. It puts merchant needs first, and best of all, it is completely FREE.',
                        ],
                        [
                            'title' => 'For Online Shoppers',
                            'icon_class' => 'fa-solid fa-cart-shopping',
                            'body' => 'Zen Cart delivers the ultimate online shopping experience. With a secure 3-step checkout process, built-in shipping quotes, and PA-DSS certification standards, customers can navigate and purchase effortlessly.',
                        ],
                        [
                            'title' => 'For Web Designers',
                            'icon_class' => 'fa-solid fa-pen-nib',
                            'body' => 'A robust template system abstracts the look-and-feel from the core code and logic. This enables designers to create truly custom solutions that integrate perfectly with existing website designs without touching the backend.',
                        ],
                        [
                            'title' => 'For Developers',
                            'icon_class' => 'fa-solid fa-code',
                            'body' => 'Deploy cutting-edge solutions based on modern standards. Combining modern PHP with powerful override protection systems, Zen Cart provides a highly flexible architecture that you can modify to suit any business need.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'comparison_panel',
                'name' => 'Project Goals',
                'sort_order' => 30,
                'data' => [
                    'eyebrow' => '',
                    'title' => 'Our Project Goals',
                    'paragraphs' => [
                        'We simply want to deliver a fast and easy-to-use shopping cart program. Everything we build revolves around making the software more powerful, yet easier to manage.',
                    ],
                    'checklist_items' => [
                        'Ease of Use: Make the program easy to set up, customize, and navigate.',
                        'Automation: Automate critical tasks like order confirmations and stock notifications.',
                        'Built-in Promotions: Native support for discount coupons, gift certificates, and group pricing.',
                        'Global Ready: Deep support for multiple languages, currencies, and complex tax structures.',
                        'Intelligent SEO: Built-in Search Engine Optimization tools, including per-product meta controls.',
                    ],
                    'section_tone' => 'gray',
                    'aside_type' => 'image',
                    'aside_title' => '',
                    'aside_theme' => 'dark',
                    'image_position' => 'start',
                    'aside_rows' => [],
                    'image_asset_id' => null,
                    'image_url' => '/storage/cms-media/about-goals.jpg',
                    'image_alt' => 'Zen Cart Goals',
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Open Source',
                'sort_order' => 40,
                'data' => [
                    'title' => '100% Free & Open Source',
                    'body' => 'Zen Cart is made available to you without charge under Version 2 of the GNU General Public License. Donations are greatly appreciated to help cover the expenses of maintenance, upgrades, the free support forum, and continued development for your online store.',
                    'theme' => 'dark',
                    'primary_label' => 'Support the Project',
                    'primary_url' => '/donate',
                    'secondary_label' => 'Join the Discussion',
                    'secondary_url' => '/forums',
                ],
            ],
            [
                'type' => 'accordion_list',
                'name' => 'Capabilities',
                'sort_order' => 50,
                'data' => [
                    'title' => 'Power and Flexibility at your Fingertips',
                    'body' => 'Every feature you need to run a high-volume store is included natively. Expand the categories below to see the full technical capabilities of Zen Cart.',
                    'items' => [
                        [
                            'title' => 'Catalog & Product Management',
                            'points' => [
                                'Easily keep catalog updated without HTML coding',
                                'Scalable to hundreds of thousands of products',
                                'Products can be marked as Free, or "Call for Price"',
                                'Products can be linked/copied to multiple categories',
                                'Add multiple product images with popup viewing',
                                'Low-Stock notifications sent to administrators',
                            ],
                        ],
                        [
                            'title' => 'Advanced Pricing & Attributes',
                            'points' => [
                                'Percentage-off, Fixed Amount, and New Price sales',
                                'Minimum/Maximum quantities and unit multiples',
                                'Quantity Discounts per-product or volume',
                                'Radio buttons, Checkboxes, Drop-downs, File Uploads',
                                'Text Pricing (price-per-word or letter)',
                                'Customer "Group Pricing" for wholesale/B2B',
                            ],
                        ],
                        [
                            'title' => 'Administration & Technology',
                            'points' => [
                                'Advanced Template System with override protection',
                                'Multiple Language & Currency Support',
                                'Search Engine friendly "Down For Maintenance" page',
                                'Intelligent meta-tag generation & spider detection',
                                'Customer Access Control (Restrict pricing/browsing)',
                                'Notifier subsystem for developers to "hook" events',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        'slug' => 'donate',
        'custom_route' => '/donate',
        'title' => 'Keep Zen Cart Free',
        'excerpt' => 'Support Zen Cart through PayPal, Zelle, or GitHub Sponsors to help fund development, documentation, and community support.',
        'seo_title' => 'Donate - Zen Cart',
        'seo_description' => 'Support Zen Cart with a donation via PayPal, Zelle, or GitHub Sponsors.',
        'published_at' => '2026-08-13 17:02:24',
        'blocks' => [
            [
                'type' => 'donation_hero',
                'name' => 'Donate Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => '',
                    'title' => 'Keep Zen Cart Free',
                    'body' => 'Show your appreciation for the great support and fantastic shopping cart software by making a donation today.',
                    'note' => 'Open source software relies on the community\'s cash donations to continue development, update the tools used, buy server space and get an occasional cup of coffee.',
                ],
            ],
            [
                'type' => 'donation_method',
                'name' => 'PayPal',
                'sort_order' => 20,
                'data' => [
                    'provider' => 'paypal',
                    'eyebrow' => 'Flexible support',
                    'title' => 'PayPal',
                    'body' => 'Make a one-time donation of any amount, or choose a monthly recurring level to provide ongoing support to Zen Cart.',
                    'cta_label' => 'Donate any amount via PayPal',
                    'cta_url' => 'https://www.paypal.com/donate/?hosted_button_id=XFE9JURMSRAHW',
                    'note' => '',
                    'tag' => '',
                    'donate_button_id' => 'WW3377SE3CEJU',
                    'amounts_heading' => 'Recurring Support',
                    'card_heading' => 'Donate to Zen Cart',
                    'amount_suffix' => 'a month',
                    'tag_label' => '',
                    'item_name' => 'Buy the Zen Cart Team some coffee!',
                    'amounts' => [],
                ],
            ],
            [
                'type' => 'donation_method',
                'name' => 'Zelle',
                'sort_order' => 30,
                'data' => [
                    'provider' => 'zelle',
                    'eyebrow' => 'Bank-to-bank',
                    'title' => 'Zelle',
                    'body' => 'Send a donation directly through your bank\'s app or website using Zelle, with no account required beyond your existing bank.',
                    'cta_label' => '',
                    'cta_url' => '',
                    'note' => 'Open your bank\'s Zelle feature, search for the tag zencart, and send your chosen amount.',
                    'tag' => 'zencart',
                    'amounts' => [],
                ],
            ],
            [
                'type' => 'donation_method',
                'name' => 'GitHub Sponsors',
                'sort_order' => 40,
                'data' => [
                    'provider' => 'github',
                    'eyebrow' => 'Developer-friendly',
                    'title' => 'GitHub Sponsors',
                    'body' => 'If you have a GitHub account, you can sponsor Zen Cart directly through GitHub Sponsors with flexible one-time or recurring amounts.',
                    'cta_label' => 'Sponsor Zen Cart on GitHub',
                    'cta_url' => 'https://github.com/sponsors/zencart',
                    'note' => 'You\'ll be taken to the GitHub Sponsors page where you can choose your contribution amount and frequency.',
                    'tag' => '',
                    'amounts' => [],
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Donate CTA',
                'sort_order' => 50,
                'data' => [
                    'title' => 'Thank You for Your Support!',
                    'body' => 'Your donation helps keep Zen Cart free and open source. Every contribution goes toward development, documentation, and community support.
<br><br>
Zen Ventures, LLC is not a registered charity, so your donation isn\'t a tax-deductible charitable contribution. Depending on your location and circumstances, it may still qualify as a deductible business expense — check with your tax advisor.',
                    'theme' => 'warm',
                    'primary_label' => '',
                    'primary_url' => '',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'compare',
        'custom_route' => '/compare',
        'title' => 'Compare Zen Cart',
        'excerpt' => 'Compare Zen Cart with Shopify, WooCommerce, and Magento across cost, ownership, and long-term control.',
        'seo_title' => 'Compare Zen Cart',
        'seo_description' => 'See how Zen Cart compares with Shopify, WooCommerce, and Magento on cost, architecture, and control.',
        'published_at' => '2026-08-13 17:02:28',
        'blocks' => [
            [
                'type' => 'comparison_matrix',
                'name' => 'Compare Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'The Strategic Choice',
                    'title' => 'How We Compare',
                    'body' => 'Most platforms charge you for the right to grow. Zen Cart gives you the engine and the keys, with zero transaction fees and no "revenue tax." Here is the quick breakdown.',
                    'headers' => [
                        [
                            'label' => 'Feature',
                            'emphasize' => false,
                        ],
                        [
                            'label' => 'Zen Cart',
                            'emphasize' => true,
                        ],
                        [
                            'label' => 'SaaS (Shopify/Wix)',
                            'emphasize' => false,
                        ],
                        [
                            'label' => 'WooCommerce',
                            'emphasize' => false,
                        ],
                    ],
                    'rows' => [
                        [
                            'feature' => 'Monthly License Fee',
                            'values' => ['$0', '$39 - $2,300+', '$0'],
                            'highlight_column' => 1,
                        ],
                        [
                            'feature' => 'Transaction Fees',
                            'values' => ['0%', '0.5% - 2.0%', '0%'],
                            'highlight_column' => 1,
                        ],
                        [
                            'feature' => 'B2B / Group Pricing',
                            'values' => ['Included (Free)', 'Paid Apps Only ($100+/mo)', 'Paid Plugins ($100+/yr)'],
                            'highlight_column' => 1,
                        ],
                        [
                            'feature' => 'Core Architecture',
                            'values' => ['Dedicated E-commerce', 'Dedicated E-commerce', 'Blog Plugin'],
                            'highlight_column' => 1,
                        ],
                        [
                            'feature' => 'Data Sovereignty',
                            'values' => ['You Own It', 'Platform Dependent', 'You Own It'],
                            'highlight_column' => 1,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'comparison_panel',
                'name' => 'Zen Cart vs. Shopify',
                'sort_order' => 20,
                'data' => [
                    'eyebrow' => 'The Ownership',
                    'title' => 'Zen Cart vs. Shopify',
                    'paragraphs' => [
                        'SaaS platforms like Shopify or BigCommerce are fantastic for beginners who want to launch a store by tomorrow afternoon. But what happens when you start succeeding?',
                        'The "Revenue Tax": As your sales volume grows, SaaS platforms take a percentage of your success through mandatory transaction fees and forced upgrades to "Enterprise" tiers. Furthermore, you do not own your data. If the platform bans your product category, your business disappears overnight. Zen Cart gives you 100% data sovereignty and 0% transaction fees.',
                    ],
                    'checklist_items' => [
                        'Never pay monthly "App Store" subscriptions for basic features.',
                        'No restrictions on what you are allowed to sell.',
                    ],
                    'section_tone' => 'cream',
                    'aside_type' => 'card',
                    'aside_title' => 'The Real Cost of Scaling',
                    'aside_theme' => 'dark',
                    'image_position' => 'end',
                    'aside_rows' => [
                        [
                            'icon_class' => 'fa-solid fa-file-invoice-dollar',
                            'label' => 'Monthly License',
                            'value' => '$0.00 Forever',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-chart-line',
                            'label' => 'Transaction Fees',
                            'value' => 'None',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-database',
                            'label' => 'Your Data',
                            'value' => '100% Private',
                        ],
                    ],
                    'image_asset_id' => null,
                    'image_url' => '',
                    'image_alt' => '',
                ],
            ],
            [
                'type' => 'comparison_panel',
                'name' => 'Zen Cart vs. WooCommerce',
                'sort_order' => 30,
                'data' => [
                    'eyebrow' => 'The Architecture',
                    'title' => 'Zen Cart vs. WooCommerce',
                    'paragraphs' => [
                        'Both platforms are open-source and free, but their foundations could not be more different. WooCommerce is a plugin bolted onto WordPress, which is a blogging platform.',
                        'When you try to run thousands of products with complex attributes through a WordPress database, performance can grind to a halt. Zen Cart, on the other hand, was engineered from line one to be a dedicated shopping cart. The database schema is strictly optimized for e-commerce, ensuring your store remains lightning-fast even as your catalog scales into the tens of thousands.',
                    ],
                    'checklist_items' => [
                        'Avoid the "Franken-store" plugin bloat.',
                        'Cleaner, safer codebase not targeted by WordPress vulnerabilities.',
                    ],
                    'section_tone' => 'gray',
                    'aside_type' => 'card',
                    'aside_title' => 'Architecture Comparison',
                    'aside_theme' => 'light',
                    'image_position' => 'start',
                    'aside_rows' => [
                        [
                            'icon_class' => '',
                            'label' => 'Platform DNA',
                            'value' => 'Zen Cart: E-commerce
Woo: Blogging',
                        ],
                        [
                            'icon_class' => '',
                            'label' => 'Database Queries',
                            'value' => 'Optimized for Products',
                        ],
                        [
                            'icon_class' => '',
                            'label' => 'B2B / Wholesale',
                            'value' => 'Native to Zen Cart',
                        ],
                    ],
                    'image_asset_id' => null,
                    'image_url' => '',
                    'image_alt' => '',
                ],
            ],
            [
                'type' => 'comparison_panel',
                'name' => 'Zen Cart vs. Magento',
                'sort_order' => 40,
                'data' => [
                    'eyebrow' => 'The Complexity',
                    'title' => 'Zen Cart vs. Magento',
                    'paragraphs' => [
                        'Magento (Adobe Commerce) is a powerhouse, but it comes with a massive "Total Cost of Ownership" (TCO).',
                        'Running Magento requires specialized, expensive hosting environments and highly specialized developers who charge enterprise rates. Zen Cart delivers the same level of deep, code-level customization and B2B flexibility, but it can run blazing fast on a standard, affordable Linux LAMP stack.',
                    ],
                    'checklist_items' => [
                        'Standard LAMP stack compatible with no enterprise hosting requirement.',
                        'Easier to customize, upgrade, and maintain over the long haul.',
                    ],
                    'section_tone' => 'cream',
                    'aside_type' => 'image',
                    'aside_title' => '',
                    'aside_theme' => 'dark',
                    'image_position' => 'end',
                    'aside_rows' => [],
                    'image_asset_id' => 14,
                    'image_url' => '/storage/cms-media/zencart-box3-transparent.png',
                    'image_alt' => 'Zen Cart Developer Control',
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Compare CTA',
                'sort_order' => 50,
                'data' => [
                    'title' => 'Ready to take control of your business?',
                    'body' => 'Stop renting your storefront. Download Zen Cart today and join tens of thousands of independent merchants who own their success.',
                    'theme' => 'dark',
                    'primary_label' => 'Download Zen Cart',
                    'primary_url' => '/download',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'home',
        'custom_route' => '/',
        'title' => 'Zen Cart Support',
        'excerpt' => 'Own your store, own your data, and launch on the open-source ecommerce platform built for long-term control.',
        'seo_title' => 'Zen Cart Support',
        'seo_description' => 'Own your store, own your data, and scale with the open-source ecommerce platform built for freedom and long-term control.',
        'published_at' => '2026-08-13 17:02:28',
        'blocks' => [
            [
                'type' => 'home_hero',
                'name' => 'Homepage Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'Open Source. Merchant Owned. Community Powered.',
                    'title' => 'E-Commerce software with room to grow.',
                    'body' => 'Build a store you control, extend it with plugins, and lean on a long-running support community without getting trapped in platform fees.',
                    'stats' => [
                        [
                            'label' => 'Track Record',
                            'value' => '20+ years',
                        ],
                        [
                            'label' => 'Extensions',
                            'value' => '2,000+ plugins',
                        ],
                        [
                            'label' => 'Ownership',
                            'value' => 'No platform fees',
                        ],
                    ],
                    'primary_label' => 'Get Started Now',
                    'primary_url' => '/download',
                    'secondary_label' => 'Learn More',
                    'secondary_url' => '/about',
                ],
            ],
            [
                'type' => 'home_value_grid',
                'name' => 'Why Teams Still Choose Zen Cart',
                'sort_order' => 20,
                'data' => [
                    'eyebrow' => 'Why Teams Still Choose Zen Cart',
                    'title' => 'Built for merchants who want leverage, not lock-in.',
                    'items' => [
                        [
                            'icon_class' => 'fa fa-graduation-cap',
                            'title' => '20+ years in the market',
                            'body' => 'Long-term maintainability matters when your store is core infrastructure.',
                            'url' => '/about',
                            'highlight' => false,
                        ],
                        [
                            'icon_class' => 'fa fa-store',
                            'title' => '50,000+ active shops',
                            'body' => 'A platform with real operational history, not just launch-day polish.',
                            'url' => '/about',
                            'highlight' => true,
                        ],
                        [
                            'icon_class' => 'fa fa-link',
                            'title' => '2,000+ plugins available',
                            'body' => 'Extend the platform without rebuilding common commerce features yourself.',
                            'url' => '/about',
                            'highlight' => false,
                        ],
                        [
                            'icon_class' => 'fa fa-search',
                            'title' => 'SEO-aware foundations',
                            'body' => 'Clean structure, strong content support, and better control over your storefront output.',
                            'url' => '/about',
                            'highlight' => false,
                        ],
                        [
                            'icon_class' => 'fa fa-handshake',
                            'title' => 'Community driven support',
                            'body' => 'Documentation, discussion, and release knowledge live in the same ecosystem.',
                            'url' => '/about',
                            'highlight' => false,
                        ],
                        [
                            'icon_class' => 'fa fa-gears',
                            'title' => 'Flexible integrations',
                            'body' => 'Connect the platform to the services and workflows that fit your business.',
                            'url' => '/about',
                            'highlight' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'home_included_grid',
                'name' => 'What\'s Included',
                'sort_order' => 30,
                'data' => [
                    'title' => 'What\'s Included',
                    'left_items' => [
                        [
                            'title' => 'It\'s free',
                            'body' => 'No monthly costs, no hidden fees, and no artificial ceiling on growth.',
                        ],
                        [
                            'title' => 'PCI DSS compliant',
                            'body' => 'Designed around established payment and security expectations.',
                        ],
                        [
                            'title' => 'Deep support history',
                            'body' => 'Years of discussion and troubleshooting are already part of the platform ecosystem.',
                        ],
                    ],
                    'right_items' => [
                        [
                            'title' => 'No restrictions',
                            'body' => 'Unlimited categories, products, transaction volume, and admin users.',
                        ],
                        [
                            'title' => 'SEO optimized',
                            'body' => 'Excellent search-friendly foundations right out of the box.',
                        ],
                        [
                            'title' => 'Constant development',
                            'body' => 'Ongoing releases and public code visibility through Github.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'home_deploy_steps',
                'name' => 'Deploy Steps',
                'sort_order' => 40,
                'data' => [
                    'eyebrow' => 'How It Works',
                    'title' => 'Deploy your website in as little as 10 minutes',
                    'steps' => [
                        [
                            'title' => '01. Download Zen Cart',
                            'body' => 'Download the zip file from our official repository',
                            'image_key' => 'cms-media/steps/step_1.jpg',
                            'image_position' => 'center 35%',
                            'open' => true,
                        ],
                        [
                            'title' => '02. Upload to your server',
                            'body' => 'Upload the file to your server',
                            'image_key' => 'cms-media/steps/step_2.jpg',
                            'image_position' => 'center center',
                            'open' => false,
                        ],
                        [
                            'title' => '03. Install on your server',
                            'body' => 'Follow the simple process to install the application on your server',
                            'image_key' => 'cms-media/steps/step_3.jpg',
                            'image_position' => 'center 30%',
                            'open' => false,
                        ],
                        [
                            'title' => '04. Ready to go',
                            'body' => 'Your site is ready for use. You can always add more plugins to extend functionality',
                            'image_key' => 'cms-media/steps/step_4.jpg',
                            'image_position' => 'center 25%',
                            'open' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'home_feature_columns',
                'name' => 'Advantage Core',
                'sort_order' => 50,
                'data' => [
                    'surface' => 'panel',
                    'eyebrow' => 'The Zen Cart Advantage',
                    'title' => 'Everything you need, built in.',
                    'body' => 'Stop paying for "apps" to do basic tasks. Zen Cart comes loaded with enterprise-grade features right out of the box, optimized for speed, accessibility, and total control.',
                    'left_items' => [
                        [
                            'label' => 'Unlimited Growth',
                            'body' => 'No limits on products, sales, or admin users.',
                        ],
                        [
                            'label' => 'B2B & Wholesale',
                            'body' => 'Built-in group pricing and volume discounts.',
                        ],
                        [
                            'label' => 'Global Commerce',
                            'body' => 'Native multi-language, multi-currency, and tax rules.',
                        ],
                        [
                            'label' => 'Marketing Engine',
                            'body' => 'Coupons, vouchers, and sophisticated "Specials" logic.',
                        ],
                    ],
                    'right_items' => [
                        [
                            'label' => 'Accessibility',
                            'body' => 'Modern core meets the highest SEO standards.',
                        ],
                        [
                            'label' => 'PHP 8.x Optimized',
                            'body' => 'High-performance code under active development.',
                        ],
                        [
                            'label' => 'Payment Ready',
                            'body' => 'PayPal, Authorize.Net and Check/Money Order out of the box.',
                        ],
                        [
                            'label' => 'True Ownership',
                            'body' => 'No monthly platform fees or "revenue tax."',
                        ],
                    ],
                    'cta_label' => 'Download Zen Cart',
                    'cta_url' => '/download',
                    'footer_note' => '',
                ],
            ],
            [
                'type' => 'home_feature_columns',
                'name' => 'Advantage Plugins',
                'sort_order' => 60,
                'data' => [
                    'surface' => 'panel_strong',
                    'eyebrow' => 'Limitless Extensibility',
                    'title' => 'Tailor-Made with Free Plugins',
                    'body' => 'Need more? The Zen Cart community provides thousands of free modules to customize your store\'s workflow, shipping, and marketing - no monthly "App Store" subscriptions required.',
                    'left_items' => [
                        [
                            'label' => 'Modern Checkout',
                            'body' => 'Conversion-optimized One-Page Checkout modules.',
                        ],
                        [
                            'label' => 'Global Payment Gateways',
                            'body' => 'Native support for Stripe, Klarna, Zelle, Braintree, and Crypto.',
                        ],
                        [
                            'label' => 'Full Logistics Integration',
                            'body' => 'Real-time rates for DHL, FedEx, UPS, USPS, and Royal Mail.',
                        ],
                        [
                            'label' => 'Advanced Media',
                            'body' => 'High-performance image management and automated thumbnail processing.',
                        ],
                    ],
                    'right_items' => [
                        [
                            'label' => 'SEO Powerhouse',
                            'body' => 'Structured Data (JSON-LD), XML Sitemaps, and SEO-friendly URLs.',
                        ],
                        [
                            'label' => 'Marketing Sync',
                            'body' => 'Built-in connectors for Google Analytics and Google Merchant Center.',
                        ],
                        [
                            'label' => 'Data Mobility',
                            'body' => 'Professional Import/Export tools for products, customers, and orders.',
                        ],
                        [
                            'label' => 'Global Compliance',
                            'body' => 'EU VAT exemption modules and advanced sales reporting.',
                        ],
                    ],
                    'cta_label' => '',
                    'cta_url' => '',
                    'footer_note' => '...and over 2,000+ more community-contributed plugins available for free.',
                ],
            ],
            [
                'type' => 'home_compare_section',
                'name' => 'Home Compare',
                'sort_order' => 70,
                'data' => [
                    'eyebrow' => 'The Open Source Choice',
                    'title' => 'Ownership vs. Subscriptions',
                    'body' => 'In a market saturated with "rented" storefronts, Zen Cart® stands as a bastion of true digital ownership. Don\'t let your platform dictate your profit.',
                    'ownership_title' => 'Why Zen Cart® Over Commercial Platforms?',
                    'ownership_paragraphs' => [
                        [
                            'text' => 'There are dozens of open-source shopping cart programs available, but none come close to the options, features, and stability of Zen Cart®.',
                            'emphasized_text' => '',
                        ],
                        [
                            'text' => 'As for commercial SaaS programs? Watch as their fees ramp up to match your sales! At some point, every growing business is forced to "jump ship" to avoid these revenue taxes.',
                            'emphasized_text' => 'Why not start with an unrestricted solution from day one?',
                        ],
                    ],
                    'ownership_cta_label' => 'View Comparison',
                    'ownership_cta_url' => '/compare',
                    'cost_title' => 'The Real Cost of Selling',
                    'cost_rows' => [
                        [
                            'icon_class' => 'fa-solid fa-file-invoice-dollar',
                            'label' => 'Monthly License',
                            'value' => '$0.00',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-chart-line',
                            'label' => 'Revenue Fees',
                            'value' => 'None',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-puzzle-piece',
                            'label' => 'Core B2C and B2B Tools',
                            'value' => 'Included',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-database',
                            'label' => 'Your Data',
                            'value' => '100% Private',
                        ],
                    ],
                    'requirements_title' => 'System Requirements (v2.1+)',
                    'requirements_rows' => [
                        [
                            'icon_class' => 'fa-brands fa-php',
                            'label' => 'PHP',
                            'value' => '8.1 - 8.3+',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-server',
                            'label' => 'Web Server',
                            'value' => 'Apache / Nginx',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-database',
                            'label' => 'Database',
                            'value' => 'MySQL 5.7+ / MariaDB',
                        ],
                        [
                            'icon_class' => 'fa-solid fa-code-branch',
                            'label' => 'Development',
                            'value' => 'Local (MAMP/XAMPP)',
                        ],
                    ],
                    'requirements_body_title' => 'Getting Started',
                    'requirements_body_paragraphs' => [
                        'A standard Linux-based hosting solution (LAMP stack) is usually all you need. While many hosts offer "one-click" installers, we recommend a manual installation for production environments to ensure maximum security and customizability.',
                    ],
                    'requirements_cta_label' => 'View Full Server Requirements',
                    'requirements_cta_url' => '{docs}/user/first_steps/server_requirements/',
                ],
            ],
        ],
    ],
    [
        'slug' => 'services-payment-processing',
        'custom_route' => '/services/payment-processing',
        'title' => 'Payment Processing',
        'excerpt' => 'Payment gateways which allow you to collect money from your customers.',
        'seo_title' => 'Payment Processing',
        'seo_description' => 'Payment gateways which allow you to collect money from your customers.',
        'published_at' => '2026-08-13 17:02:29',
        'blocks' => [
            [
                'type' => 'services_hero',
                'name' => 'Services Detail Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'Services',
                    'title' => 'Payment Processing',
                    'body' => 'Payment gateways which allow you to collect money from your customers. When you sign up using the links provided, those services pay a commission fee back to Zen Cart to help support the project.',
                    'back_label' => 'Back to Services',
                    'back_url' => '/services',
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'North American Services',
                'sort_order' => 20,
                'data' => [
                    'title' => 'North American Services',
                    'body' => '',
                    'surface' => 'soft',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'PayPal Express Checkout',
                            'body' => 'Accept credit card payments without requiring a PayPal account. Sign up for PayPal and start accepting credit card payments instantly.',
                            'url' => '{site}/partners/paypal',
                            'cta_label' => 'Sign Up with PayPal',
                            'image_url' => 'https://www.paypalobjects.com/marketing/web/logos/paypal-mark-color_new.svg',
                            'logo_html' => '',
                            'badge' => 'US - Canada - Mexico',
                            'tags' => [],
                            'dark_logo_background' => false,
                        ],
                        [
                            'name' => 'Authorize.Net',
                            'body' => 'Leading provider of Internet Protocol (IP) based payment gateway services since 1996, enabling merchants to accept credit card and electronic check payments from websites, retail stores, mail order/telephone order (MOTO) centers, or mobile devices.',
                            'url' => 'https://reseller.authorize.net/application?resellerId=10023',
                            'cta_label' => 'Apply at Authorize.Net',
                            'image_url' => 'https://www.authorize.net/conf/anet-2021/settings/wcm/templates/en-us-home-page-template/structure/_jcr_content/root/header/logo.img.jpg/1623941357644.jpg',
                            'logo_html' => '',
                            'badge' => 'US Only',
                            'tags' => ['Preferred Reseller'],
                            'dark_logo_background' => false,
                        ],
                        [
                            'name' => 'Square',
                            'body' => 'Get your money as soon as the next business day, and track your online and offline sales in one place.',
                            'url' => '{docs}/user/payment/square/',
                            'cta_label' => 'View Square Documentation',
                            'image_url' => '',
                            'logo_html' => '<svg class="h-10 w-auto" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Square"><rect width="44" height="44" rx="8" fill="#000"/><rect x="11" y="11" width="22" height="22" rx="3" fill="#fff"/></svg>',
                            'badge' => '',
                            'tags' => [],
                            'dark_logo_background' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'International Services',
                'sort_order' => 30,
                'data' => [
                    'title' => 'International Services',
                    'body' => '',
                    'surface' => 'panel',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'PayPal Express Checkout',
                            'body' => 'Accept credit card payments from customers around the world without requiring a PayPal account. Available in more than 100 countries.',
                            'url' => '{site}/partners/paypal',
                            'cta_label' => 'Sign Up with PayPal',
                            'image_url' => 'https://www.paypalobjects.com/marketing/web/logos/paypal-mark-color_new.svg',
                            'logo_html' => '',
                            'badge' => '100+ Countries',
                            'tags' => [],
                            'dark_logo_background' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Need Help Choosing?',
                'sort_order' => 40,
                'data' => [
                    'title' => 'Need Help Choosing?',
                    'body' => 'Not sure which payment gateway is right for your store? Ask the community in our forums for guidance and recommendations.',
                    'theme' => 'warm',
                    'primary_label' => 'Visit the Forums',
                    'primary_url' => '/forums',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'services-business-services',
        'custom_route' => '/services/business-services',
        'title' => 'Business Services Providers',
        'excerpt' => 'Additional features and services which may help make running your online store more successful.',
        'seo_title' => 'Business Services Providers',
        'seo_description' => 'Additional features and services which may help make running your online store more successful.',
        'published_at' => '2026-08-13 17:02:29',
        'blocks' => [
            [
                'type' => 'services_hero',
                'name' => 'Services Detail Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'Services',
                    'title' => 'Business Services Providers',
                    'body' => 'Additional features and services which may help make running your online store more successful. When you sign up using the links provided, those services pay a commission fee back to Zen Cart to help support the project.',
                    'back_label' => 'Back to Services',
                    'back_url' => '/services',
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'Marketing & Communications',
                'sort_order' => 20,
                'data' => [
                    'title' => 'Marketing & Communications',
                    'body' => '',
                    'surface' => 'soft',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'Constant Contact',
                            'body' => 'Award-winning email marketing and online survey platform trusted by over 300,000 customers. Free for lists under 5,000 subscribers with a free 60-day trial available.',
                            'url' => 'https://www.constantcontact.com',
                            'cta_label' => 'Visit Constant Contact',
                            'image_url' => '',
                            'logo_html' => '<span class="inline-flex rounded-md bg-[#0075c9] px-4 py-2 text-sm font-black text-white">Constant Contact</span>',
                            'badge' => 'Email Marketing',
                            'tags' => [],
                            'dark_logo_background' => false,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                        [
                            'name' => 'Olark',
                            'body' => 'Live chat and customer interaction platform. Understand your site visitors, engage with customers in real time, and optimize revenue through better support.',
                            'url' => 'https://www.olark.com/pricing?ref=zencart&utm_medium=referral-partner&utm_source=zencart&utm_campaign=referral-partner',
                            'cta_label' => 'Visit Olark',
                            'image_url' => 'https://cdn.prod.website-files.com/646d3c484b3d75dd99420842/6633e36dd87b8cf67a0b0e22_Olark_poppins_white.svg',
                            'logo_html' => null,
                            'badge' => 'Live Chat',
                            'tags' => [],
                            'dark_logo_background' => true,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'Affiliate Management',
                'sort_order' => 30,
                'data' => [
                    'title' => 'Affiliate Management',
                    'body' => '',
                    'surface' => 'panel',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'Jrox JAM',
                            'body' => 'Build a virtual sales force with affiliate marketing management. Recruit and manage unlimited affiliates, built-in membership management, flash-based reporting and graphs, and up to 10 commission tiers. Integrates directly with Zen Cart. Free trial available.',
                            'url' => 'https://jam.jrox.com',
                            'cta_label' => 'Visit Jrox JAM',
                            'image_url' => 'https://resource.jrox.com/images/logo-blue.png',
                            'logo_html' => '',
                            'badge' => 'Affiliate Software',
                            'tags' => [],
                            'dark_logo_background' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'Shipping & Fulfillment',
                'sort_order' => 40,
                'data' => [
                    'title' => 'Shipping & Fulfillment',
                    'body' => '',
                    'surface' => 'soft',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'Stamps.com',
                            'body' => 'Print USPS shipping labels integrated with Zen Cart. Supports all USPS mail classes including Parcel Post, Media Mail, and First Class International. Batch print up to 1,000 labels, hidden postage on labels, up to 40% discount on postage insurance, and automatic order status updates. 4-week no-risk trial with $5 free postage.',
                            'url' => 'https://stampscom.pxf.io/qWXKjg',
                            'cta_label' => 'Visit Stamps.com',
                            'image_url' => 'https://www.stamps.com/wp-content/uploads/2026/02/logo-stamps-primary-rgb-1-1.svg',
                            'logo_html' => null,
                            'badge' => 'US Postage',
                            'tags' => [],
                            'dark_logo_background' => false,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                        [
                            'name' => 'Shipwire',
                            'body' => 'Warehouse and fulfillment service with East and West Coast US and Canadian locations. Product storage in multiple warehouses, automatic order packing and shipping, inventory management, and easy cart integration.',
                            'url' => 'https://www.shipwire.com',
                            'cta_label' => 'Visit Shipwire',
                            'image_url' => 'https://www.shipwire.com/wp-content/uploads/2026/01/Shipwire_StordCompany_Black.png',
                            'logo_html' => null,
                            'badge' => 'Order Fulfillment',
                            'tags' => [],
                            'dark_logo_background' => false,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'SEO & Optimization',
                'sort_order' => 50,
                'data' => [
                    'title' => 'SEO & Optimization',
                    'body' => '',
                    'surface' => 'panel',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'WebCEO',
                            'body' => 'Online SEO toolkit to help optimize your Zen Cart store for search engines. Improve your rankings, track keywords, and analyze your site\'s performance.',
                            'url' => 'https://www.webceo.com?id=5539',
                            'cta_label' => 'Visit WebCEO',
                            'image_url' => 'https://www.webceo.com/images/site/update/webceo-company-logo.webp',
                            'logo_html' => null,
                            'badge' => 'SEO Tools',
                            'tags' => [],
                            'dark_logo_background' => false,
                            'asset_id' => null,
                            'image_asset_id' => null,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Grow Your Store',
                'sort_order' => 60,
                'data' => [
                    'title' => 'Grow Your Store',
                    'body' => 'Need advice on which services are the best fit for your business? Ask the community in our forums for guidance and recommendations.',
                    'theme' => 'warm',
                    'primary_label' => 'Visit the Forums',
                    'primary_url' => '/forums',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'services-custom-programming',
        'custom_route' => '/services/custom-programming',
        'title' => 'Custom Programming Support',
        'excerpt' => 'Need custom development work for your Zen Cart store?',
        'seo_title' => 'Custom Programming Support',
        'seo_description' => 'Need custom development work for your Zen Cart store?',
        'published_at' => '2026-08-13 17:02:29',
        'blocks' => [
            [
                'type' => 'services_hero',
                'name' => 'Services Detail Hero',
                'sort_order' => 10,
                'data' => [
                    'eyebrow' => 'Services',
                    'title' => 'Custom Programming Support',
                    'body' => 'Need custom development work for your Zen Cart store? These providers offer professional programming services and custom module development.',
                    'back_label' => 'Back to Services',
                    'back_url' => '/services',
                ],
            ],
            [
                'type' => 'provider_grid',
                'name' => 'Custom Programming Providers',
                'sort_order' => 20,
                'data' => [
                    'title' => 'Custom Programming Providers',
                    'body' => '',
                    'surface' => 'soft',
                    'columns' => 3,
                    'items' => [
                        [
                            'name' => 'ThatSoftwareGuy.com',
                            'body' => 'Experienced Zen Cart developer offering custom programming and discount modules. Whether you need a bespoke feature, a custom integration, or a tailored discount module for your store, ThatSoftwareGuy has the expertise to deliver.',
                            'url' => 'https://www.thatsoftwareguy.com/contactme_zcforum.html',
                            'cta_label' => 'Contact ThatSoftwareGuy',
                            'image_url' => 'https://www.thatsoftwareguy.com/img/site-graphics/logo.png',
                            'logo_html' => '',
                            'badge' => '',
                            'tags' => ['Upgrades', 'Discount Plugins', 'Custom Development'],
                            'dark_logo_background' => false,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'link_grid',
                'name' => 'Community Help',
                'sort_order' => 30,
                'data' => [
                    'title' => 'Community Help',
                    'body' => 'In addition to professional services, the Zen Cart community forums are a great resource for getting help with customisation. Many experienced developers and store owners are active in the forums and can offer guidance.',
                    'items' => [
                        [
                            'title' => 'Ask in the Forums',
                            'url' => '/forums',
                            'body' => 'Post your questions and get help from the community of Zen Cart developers and store owners.',
                        ],
                        [
                            'title' => 'Browse Plugins',
                            'url' => '/plugins',
                            'body' => 'Check the plugin directory for existing solutions that might meet your needs before commissioning custom work.',
                        ],
                        [
                            'title' => 'Read the Docs',
                            'url' => '{docs}/',
                            'body' => 'The Zen Cart documentation covers many customisation topics and may have the answers you need.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'cta_band',
                'name' => 'Need Custom Work?',
                'sort_order' => 40,
                'data' => [
                    'title' => 'Need Custom Work?',
                    'body' => 'Reach out to a provider above or post in the forums to find a developer who can help with your specific requirements.',
                    'theme' => 'warm',
                    'primary_label' => 'Visit the Forums',
                    'primary_url' => '/forums',
                    'secondary_label' => '',
                    'secondary_url' => '',
                ],
            ],
        ],
    ],
    [
        'slug' => 'cookie-policy',
        'custom_route' => '/cookie-policy',
        'title' => 'Cookie Policy',
        'excerpt' => 'What cookies Zen Cart uses, why we use them, and how you can manage your preferences.',
        'seo_title' => 'Cookie Policy - Zen Cart',
        'seo_description' => 'Zen Cart cookie policy: the essential, analytics, and marketing cookies we use and how to manage your consent.',
        'published_at' => '2026-08-13 17:02:30',
        'blocks' => [
            [
                'type' => 'raw_html',
                'name' => null,
                'sort_order' => 10,
                'data' => [
                    'html' => '<h2>Zen Cart&reg; Cookie Policy</h2>
<p>This Cookie Policy explains what cookies are, which cookies Zen Ventures, LLC (herein also referred to interchangeably as Zen Cart&reg;) uses on this site, and how you can manage your preferences. It should be read alongside our <a href="/privacy">Privacy Policy</a>.</p>

<h3>What Are Cookies?</h3>
<p>Cookies are small text files placed on your device when you visit a website. They allow the site to recognize your browser, remember your preferences, and keep you signed in between page requests.</p>

<h3>Cookies We Use</h3>
<p>We group cookies into the categories below. Essential cookies are always active because the site cannot function correctly without them. Analytics and marketing cookies are only set if you consent to them.</p>

<table>
  <thead>
    <tr>
      <th>Cookie</th>
      <th>Category</th>
      <th>Provider</th>
      <th>Duration</th>
      <th>Purpose</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>laravel_session</td>
      <td>Essential</td>
      <td>First-party</td>
      <td>Session</td>
      <td>Maintains your authenticated session and core app state.</td>
    </tr>
    <tr>
      <td>XSRF-TOKEN</td>
      <td>Essential</td>
      <td>First-party</td>
      <td>Session</td>
      <td>Security token used to protect forms and requests.</td>
    </tr>
    <tr>
      <td>forum_cookie_consent_v2</td>
      <td>Essential</td>
      <td>First-party</td>
      <td>1 year</td>
      <td>Stores your cookie consent choices so we can remember them across visits.</td>
    </tr>
    <tr>
      <td>_ga</td>
      <td>Analytics</td>
      <td>Google</td>
      <td>2 years</td>
      <td>Tracks anonymous usage statistics so we can understand how the site is used and improve it.</td>
    </tr>
    <tr>
      <td>_fbp</td>
      <td>Marketing</td>
      <td>Meta</td>
      <td>90 days</td>
      <td>Helps measure ad campaign effectiveness.</td>
    </tr>
  </tbody>
</table>

<h3>Managing Your Preferences</h3>
<p>You can change which optional cookies you allow at any time. Essential cookies cannot be disabled because they are required for the site to work.</p>
<p><button type="button" class="ui-btn-secondary" data-cookie-open-settings>Manage cookie preferences</button></p>
<p>If you are signed in, your consent choices are also stored on your account so they follow you between devices. You can review or update them from your profile at any time.</p>

<h3>Changes to This Policy</h3>
<p>We may update this Cookie Policy from time to time to reflect changes in the cookies we use or for legal or regulatory reasons. Material changes will be reflected by a new policy version, and continued use of the site after changes take effect constitutes acceptance of the updated policy.</p>

<h3>How To Contact Us</h3>
<p>Should you have questions or concerns about this Cookie Policy, please <!--email_off--><a href="mailto:{support_email}">contact us</a><!--/email_off-->.</p>',
                ],
            ],
        ],
    ],
];
