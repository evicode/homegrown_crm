<?php

declare(strict_types=1);

return [
    'segments' => [
        'partner' => 'Agency partner',
        'direct' => 'Direct prospect',
        'network' => 'Existing network',
    ],
    'statuses' => [
        'researching' => 'Researching',
        'ready_to_contact' => 'Ready to contact',
        'contacted' => 'Contacted',
        'later' => 'Later',
        'interested' => 'Interested',
        'conversation' => 'Conversation',
        'qualified' => 'Qualified',
        'closed_no_fit' => 'Closed — no fit',
    ],
    'transitions' => [
        'researching' => ['ready_to_contact', 'later', 'closed_no_fit'],
        'ready_to_contact' => ['contacted', 'later', 'closed_no_fit'],
        'contacted' => ['interested', 'later', 'closed_no_fit'],
        'interested' => ['conversation', 'later', 'closed_no_fit'],
        'conversation' => ['qualified', 'later', 'closed_no_fit'],
        'qualified' => ['later', 'closed_no_fit'],
        'later' => ['researching', 'ready_to_contact', 'contacted', 'interested', 'conversation', 'qualified', 'closed_no_fit'],
        'closed_no_fit' => [],
    ],
    'signals' => [
        'hiring_engineers' => 'Hiring engineers',
        'product_launch' => 'Product launch or expansion',
        'legacy_rebuild' => 'Legacy rebuild or modernization',
        'delivery_bottleneck' => 'Delivery bottleneck',
        'funding_or_growth' => 'Funding or growth event',
        'leadership_change' => 'Leadership change',
        'public_technical_pain' => 'Public technical pain',
        'other' => 'Other observed signal',
    ],
    'interaction_types' => ['email'=>'Email','phone'=>'Phone','meeting'=>'Meeting','linkedin'=>'LinkedIn','note'=>'Internal note'],
    'interaction_directions' => ['inbound'=>'Inbound','outbound'=>'Outbound','internal'=>'Internal'],
    'interaction_outcomes' => [
        'attempted_no_response'=>['label'=>'Attempted — no response','contact'=>true,'response'=>false],
        'sent_completed'=>['label'=>'Sent or completed','contact'=>true,'response'=>false],
        'meaningful_response'=>['label'=>'Meaningful response','contact'=>true,'response'=>true],
        'automated_response'=>['label'=>'Automated response','contact'=>false,'response'=>false],
        'bounce'=>['label'=>'Bounce','contact'=>false,'response'=>false],
        'wrong_recipient'=>['label'=>'Wrong recipient','contact'=>false,'response'=>false],
        'meeting_held'=>['label'=>'Meeting held','contact'=>true,'response'=>true],
        'note_only'=>['label'=>'Note only','contact'=>false,'response'=>false],
    ],
    'opportunity_offers' => ['discovery'=>'Discovery and technical assessment','implementation'=>'Implementation engagement','retainer'=>'Ongoing advisory retainer'],
    'opportunity_stages' => ['qualified'=>'Qualified','discovery_offered'=>'Discovery offered','proposal_sent'=>'Proposal sent','won'=>'Won','lost'=>'Lost'],
    'opportunity_transitions' => ['qualified'=>['discovery_offered','lost'],'discovery_offered'=>['proposal_sent','lost'],'proposal_sent'=>['won','lost'],'won'=>[],'lost'=>[]],
];
