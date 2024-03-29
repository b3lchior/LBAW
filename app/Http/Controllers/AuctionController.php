<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Auction;
use App\Models\AuctionSave;
use App\Models\Category;
USE App\Models\AuctionCategory;
use Illuminate\Auth\Access\AuthorizationException;
use App\Events\NotificationEvent;
use App\Models\Notification;
use App\Models\NotificationAuction;


class AuctionController extends Controller
{
    public static function returnAuction($id)
    {
      $auction = Auction::find($id);
      return $auction;
    }

    public function list()
    {
      $auctions = Auction::where('active', true)->paginate(9);
      return view('pages.auctionsListing', ['auctions' => $auctions]);
    }

    public function bids($id)
    {
      $auction = Auction::find($id);
      $bids = $auction->bids()->orderBy('amount', 'desc')->get();
      return view('pages.bids', ['bids' => $bids]);
    }

    public function show($id)
    {
      $auction = Auction::with(['bids', 'auctionsSaved'])->find($id);
      return view('pages.auction', ['auction' => $auction]);
    }

    public function ownedBy($user_id)
    {
      $auctions = Auction::get()->where('owner_id', $user_id);
      return view('pages.ownedAuctions', ['auctions' => $auctions]);
    }

    public function followedBy($user_id)
    {
      $allAuctions = Auction::all();
      $auctions = [];
      foreach ($allAuctions as $auction) {
        if (AuctionSave::where('user_id', $user_id)->where('auction_id', $auction->id)->exists()) {
          array_push($auctions, $auction);
        }
      }
      return view('pages.followedAuctions', ['auctions' => $auctions]);
    }

    public function follow($id)
    {
      $auctionSave = new AuctionSave;
      $auctionSave->user_id = Auth::user()->id;
      $auctionSave->auction_id = $id;
      $auctionSave->save();
      return redirect('auctions/'.$id);
    }

    public function unfollow($auction_id)
    {
      $auctionSave = AuctionSave::where('user_id', Auth::user()->id)->where('auction_id', $auction_id)->delete();
      return redirect('auctions/'.$auction_id);
    }

    public function showCreateForm()
    {
      $this->authorize('create', Auction::class);
      return view('pages.createAuction', ['id' => Auth::user()->id]);
    }

    public function create(Request $request)
    {
      $auction = new Auction();
      $this->authorize('create', $auction);
      $auction->name = $request->input('name');
      $auction->description = $request->input('description');
      $auction->starting_price = $request->input('starting_price');
      $auction->owner_id = Auth::user()->id;
      // $auction->start_date = $request->input('start_date');
      $auction->end_t = $request->input('end_t');
      $auction->save();

      return redirect('auctions/' . $auction->id);
    }

    public function showEditForm($id)
    {
      //$id = Auction::find($id);
      return view('pages.editAuction', ['id' => $id]);
    }
    public function edit(Request $request, $id)
    {
      $auction = Auction::find($id);
      $auction->name = $request->input('name');
      $auction->description = $request->input('description');
      $auction->image = $request->input('image');
      $auction->owner_id = Auth::user()->id;
      // $auction->start_date = $request->input('start_date');
      $auction->end_t = $request->input('end_t');
      $auction->save();

      return redirect('auctions/' . $auction->id);
    }

    public function delete($id) {
        $auction = Auction::find($id);


        try {
            $this->authorize('delete', $auction);
        }
        catch (AuthorizationException $exception){
            return redirect()->back()->with('error', $exception->getMessage() . " ---- The auction you're trying to delete has already been bet on.");;
        }

        event(new NotificationEvent($id));
        $this->storeAuctionCanceledNotifications($id);

        $auction->delete();

        return redirect('/');
    }

    public function listBids($id)
    {
      $auction = Auction::find($id);
      $bids = $auction->bids()->orderBy('amount', 'desc')->get();
      return view('pages.bids', ['bids' => $bids]);
    }


    // search using tsvectors
    public function ftsSearch(Request $request) {

        $perPage = 9;
        $query = Auction::query();

        if ($request->has('text') && $request->get('text') != '') {
            $search = $request->get('text');
            $formattedSearch = str_replace(' ', ' | ', $search);
            $query = Auction::whereRaw("tsvectors @@ to_tsquery('english', ?)", [$formattedSearch]);
        }

        if ($request->has('categories') && is_array($request->get('categories'))) {
            $categories = $request->get('categories');
            $query->join('auction_category', 'auction.id', '=', 'auction_category.auction_id')
                ->whereIn('auction_category.category_id', $categories);
        }



        //$query->where('active', true);
        $query->with('owner','bids');
        $query->orderBy('active', 'desc');

        $query->whereHas('owner', function ($ownerQuery) {
          $ownerQuery->where('blocked', false);
      });

      $query->whereHas('owner', function ($ownerQuery) {
        $ownerQuery->where('name', '<>', 'deleted');
    });

        $auctions = $query->paginate($perPage);

        $pagination = [
            'current_page' => $auctions->currentPage(),
            'per_page' => $auctions->perPage(),
            'total' => $auctions->total(),
        ];

        $response = [
            'data' => $auctions->items(),
            'pagination' => $pagination,
        ];



        return response()->json($response);
    }

    public function index(Request $request) {
        $auctions = json_decode($this->ftsSearch($request)->content());
        $categories = Category::all();
        return view('pages.auctionsListing', ['auctions' => $auctions,'categories' => $categories]);
    }
      //$auctions = Auction::where('name', 'LIKE', '%' . $search . '%')->get();


    public function storeAuctionCanceledNotifications($auction_id) {
        $users = AuctionSave::where('auction_id', $auction_id)->get();
        foreach ($users as $user) {
            $notification = new Notification();
            $notification->user_id = $user->user_id;
            $notification->date = now();
            $notification->message = 'Auction ' . $auction_id . ' has been canceled';
            $notification->save();

            $notificationAuction = new NotificationAuction();
            $notificationAuction->notification_id = $notification->id;
            $notificationAuction->auction_id = $auction_id;
            $notificationAuction->save();
        }
        return;
    }

}
