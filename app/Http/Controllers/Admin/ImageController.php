<?php

namespace App\Http\Controllers\Admin;

use App\Model\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Validator;

class ImageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 上传图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addImage(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'image' => 'required|image'
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $image_path = $request->file('image')->store('images');
            $image = new Image;
            $image->url = $request->url;
            $image->image = $image_path;
            $image->sort = $request->input('sort', 0);
            $image->enable = $request->input('enable', 1);
            if (!$image->save()) {
                return $this->error('保存失败');
            }
            return $this->response(['image_id' => $image->image_id]);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 修改图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateImage(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'image' => 'required|image',
                'image_id' => 'required|integer'
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $image = Image::find($request->image_id);
            if (empty($image)) {
                return $this->error('要修改的内容不存在');
            }
            $url = $request->input('url', '');
            $sort = $request->input('sort', 0);
            $enable = $request->input('enable', 1);
            $file = $request->file('image');
            if (!empty($file)) {
                $image_path = $request->file('image')->store('images');
                $image->image = $image_path;
            }
            if (!empty($url)) {
                $image->url = $request->url;
            }
            if (!empty($sort)) {
                $image->url = $request->sort;
            }
            if (!empty($enable)) {
                $image->url = $request->enable;
            }
            if (!$image->save()) {
                return $this->error('修改失败');
            }
            return $this->success('修改成功');
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 删除图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage(Request $request) {
        $image_id = $request->input('image_id', 0);
        if (empty($image_id)) {
            return $this->error('image_id必须');
        }
        $image = Image::destroy($image_id);
        if ($image) {
            return $this->success('删除成功');
        }
        return $this->error('删除失败');
    }
}
