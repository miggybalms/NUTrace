{{--
    Paging footer for a filtered asset listing (employee / department head).

    Expects $assets: the paginator from App\Support\AssetListing. It is built
    with withQueryString(), so every page link carries the tab and the search as
    well — paging never drops the filter that produced the page.

    Only the pager is rendered here: Laravel's own pagination view already says
    "Showing 1 to 10 of 25 results", and the listing header shows the filtered
    total, so a third summary would just be noise.
--}}
@if($assets->hasPages())
    <div class="mt-6 flex justify-center sm:justify-end">
        {{ $assets->links() }}
    </div>
@endif
