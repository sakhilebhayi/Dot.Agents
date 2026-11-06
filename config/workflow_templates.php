<?php

// Starting points for the diagram. They hold the shape of the work. The business chooses which agents do it.
return [
    'report-with-review' => [
        'name' => 'Report with a person checking it',
        'summary' => 'Gather the facts, check them, write it up, and a person approves before it goes further.',
        'steps' => [
            ['id' => 'gather', 'label' => 'Gather the facts', 'kind' => 'agent', 'x' => 48, 'y' => 96, 'instruction' => 'Collect the facts needed for this report. List them with where each came from.'],
            ['id' => 'check', 'label' => 'Check them', 'kind' => 'agent', 'x' => 312, 'y' => 96, 'instruction' => 'Check each fact. Mark anything you cannot confirm. Do not add new facts.'],
            ['id' => 'write', 'label' => 'Write the report', 'kind' => 'agent', 'x' => 576, 'y' => 96, 'instruction' => 'Write a one page report from the checked facts only.'],
            ['id' => 'approve', 'label' => 'A person approves', 'kind' => 'approval', 'x' => 840, 'y' => 96],
        ],
        'lines' => [['gather', 'check'], ['check', 'write'], ['write', 'approve']],
    ],
    'second-opinion' => [
        'name' => 'Two views, then one answer',
        'summary' => 'Two agents work on the same question at once, and a third compares them and says where they disagree.',
        'steps' => [
            ['id' => 'first', 'label' => 'First view', 'kind' => 'agent', 'x' => 48, 'y' => 48, 'instruction' => 'Answer the request.'],
            ['id' => 'second', 'label' => 'Second view', 'kind' => 'agent', 'x' => 48, 'y' => 192, 'instruction' => 'Answer the request. Look for what could be wrong with the obvious answer.'],
            ['id' => 'compare', 'label' => 'Compare them', 'kind' => 'agent', 'x' => 336, 'y' => 120, 'instruction' => 'Compare the two views. Say where they agree, where they conflict, and which you would rely on and why.'],
        ],
        'lines' => [['first', 'compare'], ['second', 'compare']],
    ],
    'triage' => [
        'name' => 'Sort, then send the right way',
        'summary' => 'One agent decides whether something is urgent, and the work takes a different path for each answer.',
        'steps' => [
            ['id' => 'sort', 'label' => 'Decide if it is urgent', 'kind' => 'agent', 'x' => 48, 'y' => 120, 'instruction' => 'Read the request. Start your answer with URGENT or ROUTINE, then say why in one line.'],
            ['id' => 'urgent', 'label' => 'Handle it now', 'kind' => 'agent', 'x' => 360, 'y' => 48, 'instruction' => 'Prepare what is needed to deal with this today.'],
            ['id' => 'routine', 'label' => 'Add to the queue', 'kind' => 'agent', 'x' => 360, 'y' => 192, 'instruction' => 'Summarise this for the next routine review.'],
        ],
        'lines' => [['sort', 'urgent', ['op' => 'contains', 'value' => 'urgent']], ['sort', 'routine', ['op' => 'not_contains', 'value' => 'urgent']]],
    ],
];
