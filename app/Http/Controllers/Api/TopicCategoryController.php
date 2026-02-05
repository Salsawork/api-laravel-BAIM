<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopicCategory;
use Illuminate\Http\Request;

class TopicCategoryController extends Controller
{

    public function topics($userTypeId)
    {
        $data = TopicCategory::select('id', 'name')
            ->where('user_type_id', $userTypeId)
            ->get();
    
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    

    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TopicCategory $topicCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TopicCategory $topicCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TopicCategory $topicCategory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TopicCategory $topicCategory)
    {
        //
    }
}
