@section('body-module')
    @parent
    <template id="select-user-template">
        <div class="col-5 input-group">
            <select v-model="selectBy" class="form-control col-3">
                <option value="uid">UID</option>
                <option value="name">用户名</option>
                <option value="displayName">覆盖名</option>
            </select>
            <select v-if="selectBy == 'uid'" v-model="params.uidComparison" class="col-2 form-control">
                <option>&lt;</option>
                <option>=</option>
                <option>&gt;</option>
            </select>
            <input v-if="selectBy == 'uid'" v-model="params[selectByOptionsName.uid]"
                    type="number" placeholder="4000000000" aria-label="UID" class="form-control col">
            <input v-if="selectBy == 'name'" v-model="params[selectByOptionsName.name]"
                   type="text" placeholder="n0099" aria-label="用户名" class="form-control col">
            <div v-if="selectBy == 'name'" class="input-group-append">
                <div class="input-group-text">
                    <div class="custom-checkbox custom-control">
                        <input v-model="params.nameUseRegex"
                               id="selectUserNameUseRegex" type="checkbox" value="" class="custom-control-input">
                        <label class="custom-control-label" for="selectUserNameUseRegex">正则</label>
                    </div>
                </div>
            </div>
            <input v-if="selectBy == 'displayName'" v-model="params[selectByOptionsName.displayName]"
                   type="text" placeholder="神奇🍀" aria-label="覆盖名" class="form-control col">
            <div v-if="selectBy == 'displayName'" class="input-group-append">
                <div class="input-group-text">
                    <div class="custom-checkbox custom-control">
                        <input v-model="params.displayNameUseRegex"
                               id="selectUserDisplayNameUseRegex" type="checkbox" value="" class="custom-control-input">
                        <label class="custom-control-label" for="selectUserDisplayNameUseRegex">正则</label>
                    </div>
                </div>
            </div>
        </div>
    </template>
@endsection

@section('script-module')
    @parent
    <script>
        'use strict';

        const userSelectFormComponent = Vue.component('select-user', {
            template: '#select-user-template',
            model: {
                prop: 'initialParams',
                event: 'changed'
            },
            props: {
                initialParams: {
                    type: Object
                },
                selectByOptionsName: {
                    type: Object,
                    default: function () {
                        return {
                            uid: 'uid',
                            name: 'name',
                            displayName: 'displayName'
                        };
                    }
                }
            },
            data: function () {
                return {
                    selectBy: '',
                    params: {}
                }
            },
            watch: {
                selectBy: function (selectBy) {
                    this.$data.params = {}; // empty params to prevent old value remains after selectBy changed
                    if (selectBy === 'uid') {
                        this.$data.params.uidComparison = '='; // reset to default value
                    }
                    this.$emit('changed', { selectBy, params: this.$data.params });
                },
                params: {
                    handler: function (params) {
                        this.$emit('changed', { selectBy: this.$data.selectBy, params });
                    },
                    deep: true
                }
            },
            mounted: function () {
                this.$data.selectBy = this.$props.initialParams.selectBy;
                this.$data.params = this.$props.initialParams.params;
            }
        });
    </script>
@endsection