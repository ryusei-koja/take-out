<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Auth;
use App\User;

class Payment extends Model
{
    //

    public static function setCustomer($token, $user)
    {
        \Stripe\Stripe::setApiKey(\Config::get('payment.stripe_secret_key'));
        try {
            $customer = \Stripe\Customer::create([
                'card' => $token, //カード情報はデフォルトソース？sourceにtoken入れなくて良いのか？
                'name' => $user->name,
                'description' => $user->id
            ]);
        } catch(\Stripe\Exception\CardException $e) {
            /*
             * カード登録失敗時には現段階では一律で別の登録カードを入れていただくように
             * 促すメッセージで統一。
             * カードエラーの類としては以下があるとのこと
             * １、カードが決済に失敗しました
             * ２、セキュリティーコードが間違っています
             * ３、有効期限が間違っています
             * ４、処理中にエラーが発生しました
             *  */
            return false;
        }

        $targetCustomer = null;
        if (isset($customer->id)) {
            $targetCustomer = User::find(Auth::id());//要するに当該顧客のデータをUserテーブルから引っ張りたい
            $targetCustomer->stripe_id = $customer->id;
            $targetCustomer->update();
            return true;
        }
        return false;
    }

    /**
     * Stripe上の「顧客」情報を更新するための関数
     *
     * @param String $token・・・・・Stripe上のtoken（フロントエンドで作成）
     * @param object $user ・・・・・カード登録をするユーザーの情報
     * @param object $customer・・・Stripe上に登録されている顧客オブジェクト
     * @param object $card・・・・・Stripe上に登録されているクレジットカード情報のオブジェクト
     */
    public static function updateCustomer($token, $user)
    {
        \Stripe\Stripe::setApiKey(\Config::get('payment.stripe_secret_key'));

        try {
            $customer = \Stripe\Customer::retrieve($user->stripe_id);
            // $card = $customer->sources->create(['source' => $token]);;
            // dd(['source' => $token]);
            
            // $access_token = str_random(32);
            // dd($access_token);ヘルパー関数がいる
            $card = \Stripe\Customer::createSource(//カード情報取得
                $user->stripe_id,
                ['source' => "$token"],
                //sourceはsrcから始まるやつじゃなくて良いのか？
            );
            // $card=$stripe->customers->createSource(
            //     $user->stripe_id,
            //     ['source' => "$token"],
            //   );
            // $card = \Stripe\Customer::retrieveSource($user->stripe_id,$customer->default_source);
            
           

            if (isset($customer)) {
                $customer->default_source = $card->id;
                $customer->save();
                // dd($token,$user->stripe_id,$customer,$customer->default_source,$customer->source,$card,$card->id,$card->source);
                return true;
            }
            

        } catch(\Stripe\Exception\CardException $e) {
            /*
             * カード登録失敗時には現段階では一律で別の登録カードを入れていただくように
             * 促すメッセージで統一。（メッセージ自体はController側で制御しています）
             * カードエラーの類としては
             * １、カードが決済に失敗しました
             * ２、セキュリティーコードが間違っています
             * ３、有効期限が間違っています
             * ４、処理中にエラーが発生しました
             *  */
            return false;
        }
        return true;
    }

    /**
     * Stripe上に現在登録されている顧客の「使用カード」の情報を取得するための関数
     *
     * @param String $token・・・・・Stripe上のtoken（フロントエンドで作成）
     * @param object $user ・・・・・カード登録をするユーザーの情報
     * @param object $customer・・・Stripe上に登録されている顧客オブジェクト
     * @param object $default_card・・・・・Stripe上から取得した顧客の「使用カード」オブジェクト
     */
    protected static function getDefaultcard($user)
    {
        \Stripe\Stripe::setApiKey(\Config::get('payment.stripe_secret_key'));
        // dd('aaaa');
        $default_card = null;

        if (!is_null($user->stripe_id)) {
            //stripe_id顧客情報を使って、顧客オブジェクト取得
            $customer = \Stripe\Customer::retrieve($user->stripe_id);
            //default_sourceにはカードidが入っている、Customer::retrieveSource(顧客ID,カードID)でカード情報取得(名義etc)
            $card = \Stripe\Customer::retrieveSource($user->stripe_id,$customer->default_source);
            // if (isset($customer['default_source']) && $customer['default_source']) {
                // dd($customer->sources);
                // $card = $customer->sources->data[0];
                
                $default_card = [
                    'number' => str_repeat('*', 8) . $card->last4,
                    'brand' => $card->brand,
                    'exp_month' => $card->exp_month,
                    'exp_year' => $card->exp_year,
                    'name' => $card->name,
                    'id' => $card->id,
                ];
            }
            //    else{ // return ["erorr"=>"カード登録がまだです！"];
            // }
        // }
        return $default_card;
    }

    /**
     * Stripe上に現在登録されている顧客のカード情報を削除するための関数
     *
     * @param object $user ・・・・・カード削除をするユーザーの情報
     * @param object $customer・・・Stripe上に登録されている顧客オブジェクト
     */
    protected static function deleteCard($user)
    {
        \Stripe\Stripe::setApiKey(\Config::get('payment.stripe_secret_key'));
        $customer = \Stripe\Customer::retrieve($user->stripe_id);
        $customer2 = \Stripe\Customer::retrieveSource($user->stripe_id,$customer->default_source);
        // $card = $customer->sources->data[0];
        $card = $customer2;
        // var_dump($card,"カード");
        /* card情報が存在していれば削除 */
        if ($card) {
            \Stripe\Customer::deleteSource(
                $user->stripe_id,
                $customer->default_source,
                // $card->id
            );
            return true;
        }
        return false;
    }
}
