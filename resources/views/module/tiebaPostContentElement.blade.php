@section('style-module')
    @parent
    <style>
        .tieba-image-zoom-in {
            position: relative;
        }
        .tieba-image-zoom-in::after {
            position: absolute;
            top: 25px;
            left: 30px;
            content: "\f00e"; /* fa-search-plus */
            font: 900 2em "Font Awesome 5 Free";
            opacity: 0.4;
            cursor: zoom-in;
        }
        .tieba-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            cursor: zoom-in;
        }
        .tieba-image-zoom-out {
            cursor: zoom-out;
        }
        .tieba-image-expanded {
            max-width: 80%;
            cursor: zoom-out;
        }
    </style>
@endsection

@section('script-module')
    @parent
    <script>
        'use strict';

        const $$tiebaImageZoomEventRegister = () => {
            const registerZoomInEvent = (event) => {
                let tiebaImageDOM = event.currentTarget;
                $(tiebaImageDOM).removeClass('tieba-image-zoom-in').addClass('tieba-image-zoom-out');
                $(tiebaImageDOM.children[0]).removeClass('tieba-image').addClass('tieba-image-expanded');
                $(tiebaImageDOM).off().on('click', registerZoomOutEvent);
            };
            const registerZoomOutEvent = (event) => {
                let tiebaImageDOM = event.currentTarget;
                $(tiebaImageDOM).addClass('tieba-image-zoom-in').removeClass('tieba-image-zoom-out');
                $(tiebaImageDOM.children[0]).addClass('tieba-image').removeClass('tieba-image-expanded');
                $(tiebaImageDOM).off().on('click', registerZoomInEvent);
            };
            $('.tieba-image-zoom-in').on('click', registerZoomInEvent);
            $('.tieba-image-zoom-out').on('click', registerZoomOutEvent);
        };
    </script>
@endsection
